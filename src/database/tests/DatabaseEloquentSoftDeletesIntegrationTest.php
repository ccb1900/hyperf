<?php

namespace HyperfTest\Database;

use Hyperf\Support\Carbon;
use PHPUnit\Framework\TestCase;
use Hyperf\Pagination\Paginator;
use Hyperf\Database\Query\Builder;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\Database\Capsule\Manager as DB;
use Hyperf\Database\Model\Model as Eloquent;
use Hyperf\Database\Model\SoftDeletingScope;

class DatabaseEloquentSoftDeletesIntegrationTest extends TestCase
{
    public function setUp()
    {
        Carbon::setTestNow(Carbon::now());

        $db = new DB;

        $db->addConnection([
            'driver'    => 'sqlite',
            'database'  => ':memory:',
        ]);

        $db->bootEloquent();
        $db->setAsGlobal();

        $this->createSchema();
    }

    /**
     * Setup the database schema.
     *
     * @return void
     */
    public function createSchema()
    {
        $this->schema()->create('users', function ($table) {
            $table->increments('id');
            $table->integer('group_id')->nullable();
            $table->string('email')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->schema()->create('posts', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });

        $this->schema()->create('comments', function ($table) {
            $table->increments('id');
            $table->integer('owner_id')->nullable();
            $table->string('owner_type')->nullable();
            $table->integer('post_id');
            $table->string('body');
            $table->timestamps();
            $table->softDeletes();
        });

        $this->schema()->create('addresses', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('address');
            $table->timestamps();
            $table->softDeletes();
        });

        $this->schema()->create('groups', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Tear down the database schema.
     *
     * @return void
     */
    public function tearDown()
    {
        Carbon::setTestNow(null);

        $this->schema()->drop('users');
        $this->schema()->drop('posts');
        $this->schema()->drop('comments');
    }

    /**
     * Tests...
     */
    public function testSoftDeletesAreNotRetrieved()
    {
        $this->createUsers();

        $users = SoftDeletesTestUser::all();

        $this->assertCount(1, $users);
        $this->assertEquals(2, $users->first()->id);
        $this->assertNull(SoftDeletesTestUser::find(1));
    }

    public function testSoftDeletesAreNotRetrievedFromBaseQuery()
    {
        $this->createUsers();

        $query = SoftDeletesTestUser::query()->toBase();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertCount(1, $query->get());
    }

    public function testSoftDeletesAreNotRetrievedFromBuilderHelpers()
    {
        $this->createUsers();

        $count = 0;
        $query = SoftDeletesTestUser::query();
        $query->chunk(2, function ($user) use (&$count) {
            $count += count($user);
        });
        $this->assertEquals(1, $count);

        $query = SoftDeletesTestUser::query();
        $this->assertCount(1, $query->pluck('email')->all());

        Paginator::currentPageResolver(function () {
            return 1;
        });

        $query = SoftDeletesTestUser::query();
        $this->assertCount(1, $query->paginate(2)->all());

        $query = SoftDeletesTestUser::query();
        $this->assertCount(1, $query->simplePaginate(2)->all());

        $this->assertEquals(0, SoftDeletesTestUser::where('email', 'taylorotwell@gmail.com')->increment('id'));
        $this->assertEquals(0, SoftDeletesTestUser::where('email', 'taylorotwell@gmail.com')->decrement('id'));
    }

    public function testWithTrashedReturnsAllRecords()
    {
        $this->createUsers();

        $this->assertCount(2, SoftDeletesTestUser::withTrashed()->get());
        $this->assertInstanceOf(Eloquent::class, SoftDeletesTestUser::withTrashed()->find(1));
    }

    public function testWithTrashedAcceptsAnArgument()
    {
        $this->createUsers();

        $this->assertCount(1, SoftDeletesTestUser::withTrashed(false)->get());
        $this->assertCount(2, SoftDeletesTestUser::withTrashed(true)->get());
    }

    public function testDeleteSetsDeletedColumn()
    {
        $this->createUsers();

        $this->assertInstanceOf(Carbon::class, SoftDeletesTestUser::withTrashed()->find(1)->deleted_at);
        $this->assertNull(SoftDeletesTestUser::find(2)->deleted_at);
    }

    public function testForceDeleteActuallyDeletesRecords()
    {
        $this->createUsers();
        SoftDeletesTestUser::find(2)->forceDelete();

        $users = SoftDeletesTestUser::withTrashed()->get();

        $this->assertCount(1, $users);
        $this->assertEquals(1, $users->first()->id);
    }

    public function testRestoreRestoresRecords()
    {
        $this->createUsers();
        $taylor = SoftDeletesTestUser::withTrashed()->find(1);

        $this->assertTrue($taylor->trashed());

        $taylor->restore();

        $users = SoftDeletesTestUser::all();

        $this->assertCount(2, $users);
        $this->assertNull($users->find(1)->deleted_at);
        $this->assertNull($users->find(2)->deleted_at);
    }

    public function testOnlyTrashedOnlyReturnsTrashedRecords()
    {
        $this->createUsers();

        $users = SoftDeletesTestUser::onlyTrashed()->get();

        $this->assertCount(1, $users);
        $this->assertEquals(1, $users->first()->id);
    }

    public function testOnlyWithoutTrashedOnlyReturnsTrashedRecords()
    {
        $this->createUsers();

        $users = SoftDeletesTestUser::withoutTrashed()->get();

        $this->assertCount(1, $users);
        $this->assertEquals(2, $users->first()->id);

        $users = SoftDeletesTestUser::withTrashed()->withoutTrashed()->get();

        $this->assertCount(1, $users);
        $this->assertEquals(2, $users->first()->id);
    }

    public function testFirstOrNew()
    {
        $this->createUsers();

        $result = SoftDeletesTestUser::firstOrNew(['email' => 'taylorotwell@gmail.com']);
        $this->assertNull($result->id);

        $result = SoftDeletesTestUser::withTrashed()->firstOrNew(['email' => 'taylorotwell@gmail.com']);
        $this->assertEquals(1, $result->id);
    }

    public function testFindOrNew()
    {
        $this->createUsers();

        $result = SoftDeletesTestUser::findOrNew(1);
        $this->assertNull($result->id);

        $result = SoftDeletesTestUser::withTrashed()->findOrNew(1);
        $this->assertEquals(1, $result->id);
    }

    public function testFirstOrCreate()
    {
        $this->createUsers();

        $result = SoftDeletesTestUser::withTrashed()->firstOrCreate(['email' => 'taylorotwell@gmail.com']);
        $this->assertEquals('taylorotwell@gmail.com', $result->email);
        $this->assertCount(1, SoftDeletesTestUser::all());

        $result = SoftDeletesTestUser::firstOrCreate(['email' => 'foo@bar.com']);
        $this->assertEquals('foo@bar.com', $result->email);
        $this->assertCount(2, SoftDeletesTestUser::all());
        $this->assertCount(3, SoftDeletesTestUser::withTrashed()->get());
    }

    /**
     * @throws \Exception
     */
    public function testUpdateModelAfterSoftDeleting()
    {
        $now = Carbon::now();
        $this->createUsers();

        /** @var SoftDeletesTestUser $userModel */
        $userModel = SoftDeletesTestUser::find(2);
        $userModel->delete();
        $userModel->syncOriginal();
        $this->assertEquals($now->toDateTimeString(), $userModel->getOriginal('deleted_at'));
        $this->assertNull(SoftDeletesTestUser::find(2));
        $this->assertEquals($userModel, SoftDeletesTestUser::withTrashed()->find(2));
    }

    /**
     * @throws \Exception
     */
    public function testRestoreAfterSoftDelete()
    {
        $this->createUsers();

        /** @var SoftDeletesTestUser $userModel */
        $userModel = SoftDeletesTestUser::find(2);
        $userModel->delete();
        $userModel->syncOriginal();
        $userModel->restore();

        $this->assertEquals($userModel->id, SoftDeletesTestUser::find(2)->id);
    }

    /**
     * @throws \Exception
     */
    public function testSoftDeleteAfterRestoring()
    {
        $this->createUsers();

        /** @var SoftDeletesTestUser $userModel */
        $userModel = SoftDeletesTestUser::withTrashed()->find(1);
        $userModel->restore();
        $this->assertEquals($userModel->deleted_at, SoftDeletesTestUser::find(1)->deleted_at);
        $this->assertEquals($userModel->getOriginal('deleted_at'), SoftDeletesTestUser::find(1)->deleted_at);
        $userModel->delete();
        $userModel->syncOriginal();
        $this->assertNull(SoftDeletesTestUser::find(1));
        $this->assertEquals($userModel->deleted_at, SoftDeletesTestUser::withTrashed()->find(1)->deleted_at);
        $this->assertEquals($userModel->getOriginal('deleted_at'), SoftDeletesTestUser::withTrashed()->find(1)->deleted_at);
    }

    public function testUpdateOrCreate()
    {
        $this->createUsers();

        $result = SoftDeletesTestUser::updateOrCreate(['email' => 'foo@bar.com'], ['email' => 'bar@baz.com']);
        $this->assertEquals('bar@baz.com', $result->email);
        $this->assertCount(2, SoftDeletesTestUser::all());

        $result = SoftDeletesTestUser::withTrashed()->updateOrCreate(['email' => 'taylorotwell@gmail.com'], ['email' => 'foo@bar.com']);
        $this->assertEquals('foo@bar.com', $result->email);
        $this->assertCount(2, SoftDeletesTestUser::all());
        $this->assertCount(3, SoftDeletesTestUser::withTrashed()->get());
    }

    public function testHasOneRelationshipCanBeSoftDeleted()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $abigail->address()->create(['address' => 'Laravel avenue 43']);

        // delete on builder
        $abigail->address()->delete();

        $abigail = $abigail->fresh();

        $this->assertNull($abigail->address);
        $this->assertEquals('Laravel avenue 43', $abigail->address()->withTrashed()->first()->address);

        // restore
        $abigail->address()->withTrashed()->restore();

        $abigail = $abigail->fresh();

        $this->assertEquals('Laravel avenue 43', $abigail->address->address);

        // delete on model
        $abigail->address->delete();

        $abigail = $abigail->fresh();

        $this->assertNull($abigail->address);
        $this->assertEquals('Laravel avenue 43', $abigail->address()->withTrashed()->first()->address);

        // force delete
        $abigail->address()->withTrashed()->forceDelete();

        $abigail = $abigail->fresh();

        $this->assertNull($abigail->address);
    }

    public function testBelongsToRelationshipCanBeSoftDeleted()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $group = SoftDeletesTestGroup::create(['name' => 'admin']);
        $abigail->group()->associate($group);
        $abigail->save();

        // delete on builder
        $abigail->group()->delete();

        $abigail = $abigail->fresh();

        $this->assertNull($abigail->group);
        $this->assertEquals('admin', $abigail->group()->withTrashed()->first()->name);

        // restore
        $abigail->group()->withTrashed()->restore();

        $abigail = $abigail->fresh();

        $this->assertEquals('admin', $abigail->group->name);

        // delete on model
        $abigail->group->delete();

        $abigail = $abigail->fresh();

        $this->assertNull($abigail->group);
        $this->assertEquals('admin', $abigail->group()->withTrashed()->first()->name);

        // force delete
        $abigail->group()->withTrashed()->forceDelete();

        $abigail = $abigail->fresh();

        $this->assertNull($abigail->group()->withTrashed()->first());
    }

    public function testHasManyRelationshipCanBeSoftDeleted()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $abigail->posts()->create(['title' => 'First Title']);
        $abigail->posts()->create(['title' => 'Second Title']);

        // delete on builder
        $abigail->posts()->where('title', 'Second Title')->delete();

        $abigail = $abigail->fresh();

        $this->assertCount(1, $abigail->posts);
        $this->assertEquals('First Title', $abigail->posts->first()->title);
        $this->assertCount(2, $abigail->posts()->withTrashed()->get());

        // restore
        $abigail->posts()->withTrashed()->restore();

        $abigail = $abigail->fresh();

        $this->assertCount(2, $abigail->posts);

        // force delete
        $abigail->posts()->where('title', 'Second Title')->forceDelete();

        $abigail = $abigail->fresh();

        $this->assertCount(1, $abigail->posts);
        $this->assertCount(1, $abigail->posts()->withTrashed()->get());
    }

    public function testSecondLevelRelationshipCanBeSoftDeleted()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post = $abigail->posts()->create(['title' => 'First Title']);
        $post->comments()->create(['body' => 'Comment Body']);

        $abigail->posts()->first()->comments()->delete();

        $abigail = $abigail->fresh();

        $this->assertCount(0, $abigail->posts()->first()->comments);
        $this->assertCount(1, $abigail->posts()->first()->comments()->withTrashed()->get());
    }

    public function testWhereHasWithDeletedRelationship()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post = $abigail->posts()->create(['title' => 'First Title']);

        $users = SoftDeletesTestUser::where('email', 'taylorotwell@gmail.com')->has('posts')->get();
        $this->assertCount(0, $users);

        $users = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->has('posts')->get();
        $this->assertCount(1, $users);

        $users = SoftDeletesTestUser::where('email', 'doesnt@exist.com')->orHas('posts')->get();
        $this->assertCount(1, $users);

        $users = SoftDeletesTestUser::whereHas('posts', function ($query) {
            $query->where('title', 'First Title');
        })->get();
        $this->assertCount(1, $users);

        $users = SoftDeletesTestUser::whereHas('posts', function ($query) {
            $query->where('title', 'Another Title');
        })->get();
        $this->assertCount(0, $users);

        $users = SoftDeletesTestUser::where('email', 'doesnt@exist.com')->orWhereHas('posts', function ($query) {
            $query->where('title', 'First Title');
        })->get();
        $this->assertCount(1, $users);

        // With Post Deleted...

        $post->delete();
        $users = SoftDeletesTestUser::has('posts')->get();
        $this->assertCount(0, $users);
    }

    public function testWhereHasWithNestedDeletedRelationshipAndOnlyTrashedCondition()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post = $abigail->posts()->create(['title' => 'First Title']);
        $post->delete();

        $users = SoftDeletesTestUser::has('posts')->get();
        $this->assertCount(0, $users);

        $users = SoftDeletesTestUser::whereHas('posts', function ($q) {
            $q->onlyTrashed();
        })->get();
        $this->assertCount(1, $users);

        $users = SoftDeletesTestUser::whereHas('posts', function ($q) {
            $q->withTrashed();
        })->get();
        $this->assertCount(1, $users);
    }

    public function testWhereHasWithNestedDeletedRelationship()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post = $abigail->posts()->create(['title' => 'First Title']);
        $comment = $post->comments()->create(['body' => 'Comment Body']);
        $comment->delete();

        $users = SoftDeletesTestUser::has('posts.comments')->get();
        $this->assertCount(0, $users);

        $users = SoftDeletesTestUser::doesntHave('posts.comments')->get();
        $this->assertCount(1, $users);
    }

    public function testWhereDoesntHaveWithNestedDeletedRelationship()
    {
        $this->createUsers();

        $users = SoftDeletesTestUser::doesntHave('posts.comments')->get();
        $this->assertCount(1, $users);
    }

    public function testWhereHasWithNestedDeletedRelationshipAndWithTrashedCondition()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUserWithTrashedPosts::where('email', 'abigailotwell@gmail.com')->first();
        $post = $abigail->posts()->create(['title' => 'First Title']);
        $post->delete();

        $users = SoftDeletesTestUserWithTrashedPosts::has('posts')->get();
        $this->assertCount(1, $users);
    }

    public function testWithCountWithNestedDeletedRelationshipAndOnlyTrashedCondition()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post1 = $abigail->posts()->create(['title' => 'First Title']);
        $post1->delete();
        $abigail->posts()->create(['title' => 'Second Title']);
        $abigail->posts()->create(['title' => 'Third Title']);

        $user = SoftDeletesTestUser::withCount('posts')->orderBy('postsCount', 'desc')->first();
        $this->assertEquals(2, $user->posts_count);

        $user = SoftDeletesTestUser::withCount(['posts' => function ($q) {
            $q->onlyTrashed();
        }])->orderBy('postsCount', 'desc')->first();
        $this->assertEquals(1, $user->posts_count);

        $user = SoftDeletesTestUser::withCount(['posts' => function ($q) {
            $q->withTrashed();
        }])->orderBy('postsCount', 'desc')->first();
        $this->assertEquals(3, $user->posts_count);

        $user = SoftDeletesTestUser::withCount(['posts' => function ($q) {
            $q->withTrashed()->where('title', 'First Title');
        }])->orderBy('postsCount', 'desc')->first();
        $this->assertEquals(1, $user->posts_count);

        $user = SoftDeletesTestUser::withCount(['posts' => function ($q) {
            $q->where('title', 'First Title');
        }])->orderBy('postsCount', 'desc')->first();
        $this->assertEquals(0, $user->posts_count);
    }

    public function testOrWhereWithSoftDeleteConstraint()
    {
        $this->createUsers();

        $users = SoftDeletesTestUser::where('email', 'taylorotwell@gmail.com')->orWhere('email', 'abigailotwell@gmail.com');
        $this->assertEquals(['abigailotwell@gmail.com'], $users->pluck('email')->all());
    }

    public function testMorphToWithTrashed()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post1 = $abigail->posts()->create(['title' => 'First Title']);
        $post1->comments()->create([
            'body' => 'Comment Body',
            'owner_type' => SoftDeletesTestUser::class,
            'owner_id' => $abigail->id,
        ]);

        $abigail->delete();

        $comment = SoftDeletesTestCommentWithTrashed::with(['owner' => function ($q) {
            $q->withoutGlobalScope(SoftDeletingScope::class);
        }])->first();

        $this->assertEquals($abigail->email, $comment->owner->email);

        $comment = SoftDeletesTestCommentWithTrashed::with(['owner' => function ($q) {
            $q->withTrashed();
        }])->first();

        $this->assertEquals($abigail->email, $comment->owner->email);

        $comment = TestCommentWithoutSoftDelete::with(['owner' => function ($q) {
            $q->withTrashed();
        }])->first();

        $this->assertEquals($abigail->email, $comment->owner->email);
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testMorphToWithBadMethodCall()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post1 = $abigail->posts()->create(['title' => 'First Title']);

        $post1->comments()->create([
            'body' => 'Comment Body',
            'owner_type' => SoftDeletesTestUser::class,
            'owner_id' => $abigail->id,
        ]);

        TestCommentWithoutSoftDelete::with(['owner' => function ($q) {
            $q->thisMethodDoesNotExist();
        }])->first();
    }

    public function testMorphToWithConstraints()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post1 = $abigail->posts()->create(['title' => 'First Title']);
        $post1->comments()->create([
            'body' => 'Comment Body',
            'owner_type' => SoftDeletesTestUser::class,
            'owner_id' => $abigail->id,
        ]);

        $comment = SoftDeletesTestCommentWithTrashed::with(['owner' => function ($q) {
            $q->where('email', 'taylorotwell@gmail.com');
        }])->first();

        $this->assertNull($comment->owner);
    }

    public function testMorphToWithoutConstraints()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post1 = $abigail->posts()->create(['title' => 'First Title']);
        $post1->comments()->create([
            'body' => 'Comment Body',
            'owner_type' => SoftDeletesTestUser::class,
            'owner_id' => $abigail->id,
        ]);

        $comment = SoftDeletesTestCommentWithTrashed::with('owner')->first();

        $this->assertEquals($abigail->email, $comment->owner->email);

        $abigail->delete();
        $comment = SoftDeletesTestCommentWithTrashed::with('owner')->first();

        $this->assertNull($comment->owner);
    }

    public function testMorphToNonSoftDeletingModel()
    {
        $taylor = TestUserWithoutSoftDelete::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $post1 = $taylor->posts()->create(['title' => 'First Title']);
        $post1->comments()->create([
            'body' => 'Comment Body',
            'owner_type' => TestUserWithoutSoftDelete::class,
            'owner_id' => $taylor->id,
        ]);

        $comment = SoftDeletesTestCommentWithTrashed::with('owner')->first();

        $this->assertEquals($taylor->email, $comment->owner->email);

        $taylor->delete();
        $comment = SoftDeletesTestCommentWithTrashed::with('owner')->first();

        $this->assertNull($comment->owner);
    }

    /**
     * Helpers...
     */
    protected function createUsers()
    {
        $taylor = SoftDeletesTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        SoftDeletesTestUser::create(['id' => 2, 'email' => 'abigailotwell@gmail.com']);

        $taylor->delete();
    }

    /**
     * Get a database connection instance.
     *
     * @return \Hyperf\Database\Connection
     */
    protected function connection()
    {
        return Eloquent::getConnectionResolver()->connection();
    }

    /**
     * Get a schema builder instance.
     *
     * @return \Hyperf\Database\Schema\Builder
     */
    protected function schema()
    {
        return $this->connection()->getSchemaBuilder();
    }
}

/**
 * Eloquent Models...
 */
class TestUserWithoutSoftDelete extends Eloquent
{
    protected $table = 'users';
    protected $guarded = [];

    public function posts()
    {
        return $this->hasMany(SoftDeletesTestPost::class, 'user_id');
    }
}

/**
 * Eloquent Models...
 */
class SoftDeletesTestUser extends Eloquent
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $table = 'users';
    protected $guarded = [];

    public function posts()
    {
        return $this->hasMany(SoftDeletesTestPost::class, 'user_id');
    }

    public function address()
    {
        return $this->hasOne(SoftDeletesTestAddress::class, 'user_id');
    }

    public function group()
    {
        return $this->belongsTo(SoftDeletesTestGroup::class, 'group_id');
    }
}

class SoftDeletesTestUserWithTrashedPosts extends Eloquent
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $table = 'users';
    protected $guarded = [];

    public function posts()
    {
        return $this->hasMany(SoftDeletesTestPost::class, 'user_id')->withTrashed();
    }
}

/**
 * Eloquent Models...
 */
class SoftDeletesTestPost extends Eloquent
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $table = 'posts';
    protected $guarded = [];

    public function comments()
    {
        return $this->hasMany(SoftDeletesTestComment::class, 'post_id');
    }
}

/**
 * Eloquent Models...
 */
class TestCommentWithoutSoftDelete extends Eloquent
{
    protected $table = 'comments';
    protected $guarded = [];

    public function owner()
    {
        return $this->morphTo();
    }
}

/**
 * Eloquent Models...
 */
class SoftDeletesTestComment extends Eloquent
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $table = 'comments';
    protected $guarded = [];

    public function owner()
    {
        return $this->morphTo();
    }
}

class SoftDeletesTestCommentWithTrashed extends Eloquent
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $table = 'comments';
    protected $guarded = [];

    public function owner()
    {
        return $this->morphTo();
    }
}

/**
 * Eloquent Models...
 */
class SoftDeletesTestAddress extends Eloquent
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $table = 'addresses';
    protected $guarded = [];
}

/**
 * Eloquent Models...
 */
class SoftDeletesTestGroup extends Eloquent
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $table = 'groups';
    protected $guarded = [];

    public function users()
    {
        $this->hasMany(SoftDeletesTestUser::class);
    }
}