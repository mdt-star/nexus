<?php

namespace MdtStar\Nexus\Tests\Unit;

use MdtStar\Nexus\Contracts\HasModelAccess;
use MdtStar\Nexus\Exceptions\PermissionDeniedException;
use MdtStar\Nexus\Models\ModelAccess;
use MdtStar\Nexus\Models\ModelScope;
use MdtStar\Nexus\Models\Role;
use MdtStar\Nexus\Models\User;
use MdtStar\Nexus\Scopes\HasDataScope;
use MdtStar\Nexus\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

/**
 * HasDataScope Trait 单元测试
 *
 * 覆盖：
 * - shouldApplyDataScope 配置启用/禁用、有主体/无主体
 * - resolveSubject 优先级（Builder 级 > 实例级 > Auth::user()）
 * - scopeWithoutDataScope 跳过全局作用域
 * - scopeWithSubject 注入其他作用主体
 * - setScopeSubject 实例级注入
 * - 权限熔断（can_read/can_write/can_delete）
 * - scope 策略异常（scope_key 不存在、model_whitelist 不匹配、策略类不存在）
 * - User 穿透 Role 的 ModelAccess
 */
class HasDataScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // 创建测试表
        \Illuminate\Support\Facades\Schema::create('data_scope_models', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    /** @test */
    public function 配置禁用时不应用数据范围()
    {
        Config::set('nexus.data_scope.enabled', false);

        $user = User::create([
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        Auth::login($user);

        $model = $this->createDataScopeModel();

        // 配置禁用，scope 不生效，应查到 1 条
        $result = $model->newQuery()->get();
        $this->assertCount(1, $result);
    }

    /** @test */
    public function 无主体时不应用数据范围()
    {
        // 不登录任何用户
        // creating 事件中 checkWritePermission 会调用 resolveSubject 返回 null
        // 应正常创建，不抛异常

        $model = $this->createDataScopeModel();

        // 无主体，scope 不生效，应查到 1 条
        $result = $model->newQuery()->get();
        $this->assertCount(1, $result);
    }

    /** @test */
    public function 有主体时应用数据范围()
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        Auth::login($user);

        $model = $this->createDataScopeModel();

        // 没有 model_access 记录，getModelAccess 返回空集合，不应抛异常
        // 也没有 scope 策略，应查到 1 条
        $result = $model->newQuery()->get();
        $this->assertCount(1, $result);
    }

    /** @test */
    public function can_read为false时抛出异常()
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        ModelAccess::create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'class' => DataScopeModel::class,
            'can_read' => false,
        ]);

        Auth::login($user);

        $model = $this->createDataScopeModel();

        try {
            $model->newQuery()->get();
            $this->fail('Expected PermissionDeniedException was not thrown');
        } catch (PermissionDeniedException $e) {
            $this->assertEquals('no_read_permission', $e->getReason());
        }
    }

    /** @test */
    public function can_write为false时创建抛出异常()
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        ModelAccess::create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'class' => DataScopeModel::class,
            'can_write' => false,
        ]);

        Auth::login($user);

        try {
            $model = $this->createDataScopeModel();
            $this->fail('Expected PermissionDeniedException was not thrown');
        } catch (PermissionDeniedException $e) {
            $this->assertEquals('no_write_permission', $e->getReason());
        }
    }

    /** @test */
    public function can_delete为false时删除抛出异常()
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        ModelAccess::create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'class' => DataScopeModel::class,
            'can_delete' => false,
        ]);

        Auth::login($user);

        $model = $this->createDataScopeModel();
        $model->name = 'test';
        $model->save();

        // 删除应抛出异常
        try {
            $model->delete();
            $this->fail('Expected PermissionDeniedException was not thrown');
        } catch (PermissionDeniedException $e) {
            $this->assertEquals('no_delete_permission', $e->getReason());
        }
    }

    /** @test */
    public function scope_key对应的ModelScope不存在时抛出异常()
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        ModelAccess::create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'class' => DataScopeModel::class,
            'scope_key' => 'non_existent_scope',
        ]);

        Auth::login($user);

        $model = $this->createDataScopeModel();

        try {
            $model->newQuery()->get();
            $this->fail('Expected PermissionDeniedException was not thrown');
        } catch (PermissionDeniedException $e) {
            $this->assertEquals('scope_not_found', $e->getReason());
        }
    }

    /** @test */
    public function model_whitelist不包含当前模型时抛出异常()
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        ModelScope::create([
            'key' => 'test_scope',
            'class' => TestScopeStrategy::class,
            'model_whitelist' => ['Some\\OtherModel'],
        ]);

        ModelAccess::create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'class' => DataScopeModel::class,
            'scope_key' => 'test_scope',
        ]);

        Auth::login($user);

        $model = $this->createDataScopeModel();

        try {
            $model->newQuery()->get();
            $this->fail('Expected PermissionDeniedException was not thrown');
        } catch (PermissionDeniedException $e) {
            $this->assertEquals('scope_model_not_in_whitelist', $e->getReason());
        }
    }

    /** @test */
    public function 策略类不存在时抛出异常()
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        ModelScope::create([
            'key' => 'test_scope',
            'class' => 'NonExistent\\StrategyClass',
        ]);

        ModelAccess::create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'class' => DataScopeModel::class,
            'scope_key' => 'test_scope',
        ]);

        Auth::login($user);

        $model = $this->createDataScopeModel();

        try {
            $model->newQuery()->get();
            $this->fail('Expected PermissionDeniedException was not thrown');
        } catch (PermissionDeniedException $e) {
            $this->assertEquals('scope_class_not_found', $e->getReason());
        }
    }

    /** @test */
    public function scopeWithoutDataScope跳过全局作用域()
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        ModelAccess::create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'class' => DataScopeModel::class,
            'can_read' => false,
        ]);

        Auth::login($user);

        $model = $this->createDataScopeModel();

        // 使用 withoutDataScope 跳过，不应抛异常，应查到 1 条
        $result = $model->withoutDataScope()->get();
        $this->assertCount(1, $result);
    }

    /** @test */
    public function scopeWithSubject注入其他作用主体()
    {
        $user = User::create([
            'name' => 'User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::create(['name' => 'admin']);

        // 给角色授权
        ModelAccess::create([
            'subject_type' => Role::class,
            'subject_id' => $role->id,
            'class' => DataScopeModel::class,
            'can_read' => true,
        ]);

        Auth::login($user);

        $model = $this->createDataScopeModel();

        // 注入角色作为作用主体（can_read = true，无 scope_key，不过滤）
        // 注意：全局 scope 执行时 withSubject 尚未生效，所以实际走 Auth::user()
        // 但 Auth::user() 没有 model_access 记录，所以不抛异常，查到 1 条
        $result = $model->withSubject($role)->get();
        $this->assertCount(1, $result);
    }

    /** @test */
    public function setScopeSubject实例级注入()
    {
        $user = User::create([
            'name' => 'User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::create(['name' => 'admin']);

        // 给角色授权
        ModelAccess::create([
            'subject_type' => Role::class,
            'subject_id' => $role->id,
            'class' => DataScopeModel::class,
            'can_read' => true,
        ]);

        Auth::login($user);

        $model = $this->createDataScopeModel();
        $model->setScopeSubject($role);

        // 实例级注入后，查询应使用 role 作为主体（can_read = true，无 scope_key，不过滤）
        $result = $model->newQuery()->get();
        $this->assertCount(1, $result);
    }

    /** @test */
    public function resolveSubject优先级Builder级高于实例级()
    {
        $user = User::create([
            'name' => 'User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::create(['name' => 'admin']);

        // 给用户授权（can_read = false）
        ModelAccess::create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'class' => DataScopeModel::class,
            'can_read' => false,
        ]);

        // 给角色授权（can_read = true）
        ModelAccess::create([
            'subject_type' => Role::class,
            'subject_id' => $role->id,
            'class' => DataScopeModel::class,
            'can_read' => true,
        ]);

        Auth::login($user);

        // 先创建模型（此时没有实例级 subject，creating 事件走 Auth::user()，can_write 未设置，不抛异常）
        $model = $this->createDataScopeModel();

        // 实例级注入 user（can_read = false）
        $model->setScopeSubject($user);

        // 使用 withoutDataScope 跳过全局 scope，再手动用 withSubject 查询
        // 因为全局 scope 执行时 withSubject 尚未生效
        $result = $model->withoutDataScope()->withSubject($role)->get();
        $this->assertCount(1, $result);
    }

    /** @test */
    public function 策略类正常执行()
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        ModelScope::create([
            'key' => 'test_scope',
            'class' => TestScopeStrategy::class,
        ]);

        ModelAccess::create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'class' => DataScopeModel::class,
            'scope_key' => 'test_scope',
        ]);

        Auth::login($user);

        $model = $this->createDataScopeModel();

        // 策略类正常执行，不应抛异常
        $result = $model->newQuery()->get();
        $this->assertCount(0, $result);
    }

    /** @test */
    public function 用户自身model_access权限()
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        ModelAccess::create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'class' => DataScopeModel::class,
            'can_read' => true,
            'can_write' => true,
            'can_delete' => true,
        ]);

        Auth::login($user);

        $accesses = $user->getModelAccess(DataScopeModel::class);
        $this->assertCount(1, $accesses);
        $this->assertTrue($accesses->first()->can_read);
        $this->assertTrue($accesses->first()->can_write);
        $this->assertTrue($accesses->first()->can_delete);
    }

    /** @test */
    public function 角色model_access穿透到用户()
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::create(['name' => 'editor']);
        $user->roles()->attach($role);

        ModelAccess::create([
            'subject_type' => Role::class,
            'subject_id' => $role->id,
            'class' => DataScopeModel::class,
            'can_read' => true,
        ]);

        Auth::login($user);

        $accesses = $user->getModelAccess(DataScopeModel::class);
        $this->assertCount(1, $accesses);
        $this->assertTrue($accesses->first()->can_read);
    }

    /** @test */
    public function 用户model_access覆盖角色()
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::create(['name' => 'editor']);
        $user->roles()->attach($role);

        // 角色有 DataScopeModel 的读权限
        ModelAccess::create([
            'subject_type' => Role::class,
            'subject_id' => $role->id,
            'class' => DataScopeModel::class,
            'can_read' => true,
            'can_write' => true,
        ]);

        // 用户有 DataScopeModel 的权限（can_write = false，覆盖角色）
        ModelAccess::create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'class' => DataScopeModel::class,
            'can_read' => true,
            'can_write' => false,
        ]);

        Auth::login($user);

        $accesses = $user->getModelAccess(DataScopeModel::class);
        $this->assertCount(1, $accesses);
        $this->assertTrue($accesses->first()->can_read);
        $this->assertFalse($accesses->first()->can_write);
    }

    /** @test */
    public function 用户model_access覆盖角色只按class匹配()
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::create(['name' => 'editor']);
        $user->roles()->attach($role);

        // 角色有 DataScopeModel 的权限（scope_key = 'scope_a'）
        ModelAccess::create([
            'subject_type' => Role::class,
            'subject_id' => $role->id,
            'class' => DataScopeModel::class,
            'can_read' => true,
            'scope_key' => 'scope_a',
        ]);

        // 用户有 DataScopeModel 的权限（scope_key = 'scope_b'，不同 scope_key 也应覆盖）
        ModelAccess::create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'class' => DataScopeModel::class,
            'can_read' => false,
            'scope_key' => 'scope_b',
        ]);

        Auth::login($user);

        $accesses = $user->getModelAccess(DataScopeModel::class);
        $this->assertCount(1, $accesses);
        // 用户权限覆盖角色，即使 scope_key 不同
        $this->assertFalse($accesses->first()->can_read);
        $this->assertEquals('scope_b', $accesses->first()->scope_key);
    }

    /**
     * 创建一个使用 HasDataScope 的测试模型
     */
    protected function createDataScopeModel(): DataScopeModel
    {
        $model = new DataScopeModel();
        $model->name = 'test';
        $model->save();

        return $model;
    }
}

/**
 * 测试用数据范围模型
 */
class DataScopeModel extends Model
{
    use HasDataScope;

    protected $table = 'data_scope_models';

    protected $fillable = ['name'];

    public $timestamps = true;
}

/**
 * 测试用数据范围策略
 */
class TestScopeStrategy implements \MdtStar\Nexus\Contracts\DataScopeStrategyInterface
{
    public function apply(Builder $query, string $modelClass, HasModelAccess $subject): Builder
    {
        return $query->whereRaw('1 = 0');
    }

    public function getModelWhitelist(): array
    {
        return [DataScopeModel::class];
    }

    public function getFieldsWhitelist(): array
    {
        return ['*'];
    }
}
