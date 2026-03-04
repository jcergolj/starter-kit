<laravel-boost-guidelines>
=== .ai/controllers rules ===

# Controller & Form Request Conventions

> Controller patterns, Form Request validation, and routing conventions

## Controller Location & Structure

```
app/Http/Controllers/
├── Controller.php (base class)
├── PostController.php
├── CommentController.php
├── Admin/
│   ├── PostController.php
│   ├── UserController.php
│   └── CategoryController.php
├── Webhooks/
│   └── PaymentController.php
└── Api/
    ├── PostController.php
    └── CommentController.php
```

## Type Hints Required

**Parameters and return types explicit:**

```php
public function index(Request $request): View
{
    $posts = Post::with(['author', 'tags'])
        ->published()
        ->searchBy($request->search)
        ->paginate(15);

    return view('posts.index', ['posts' => $posts]);
}

public function update(UpdatePostRequest $request, Post $post): RedirectResponse
{
    Gate::authorize('update', $post);
    $post->update($request->validated());
    msg_success(trans('controllers.post_controller.updated'));
    return to_intended_route('posts.index');
}

public function destroy(Post $post): RedirectResponse
{
    Gate::authorize('delete', $post);
    $post->delete();
    msg_success(trans('controllers.post_controller.deleted'));
    return to_intended_route('posts.index');
}
```

## Form Request Validation

**Always use Form Requests** (not inline validation):

```
app/Http/Requests/
├── AppFormRequest.php (base class)
├── Admin/
│   ├── StorePostRequest.php
│   └── UpdatePostRequest.php
├── StoreCommentRequest.php
└── UpdateProfileRequest.php
```

**Base class:**

```php
// app/Http/Requests/AppFormRequest.php
class AppFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;  // Handle auth in controllers/policies
    }
}
```

## Validation Rules

**Use array-based rules** (not string):

```php
public function rules(): array
{
    return [
        'title' => ['required', 'string', 'max:255'],
        'content' => ['required', 'string'],
        'status' => ['required', new Enum(StatusEnum::class)],
        'category' => ['required', new Enum(CategoryEnum::class)],
        'excerpt' => ['nullable', 'string', 'max:500'],
        'published_at' => ['nullable', 'date', 'after_or_equal:today'],
        'tags' => ['nullable', 'array'],
        'tags.*' => ['exists:tags,id'],
        'slug' => [
            'nullable',
            'string',
            Rule::unique('posts', 'slug')->ignore($this->post),
        ],
    ];
}
```

## Data Preparation

**Use `prepareForValidation()` for cleanup:**

```php
protected function prepareForValidation(): void
{
    $this->merge([
        'slug' => Str::of($this->title)->slug()->toString(),
        'email' => Str::of($this->email)
            ->trim()
            ->lower()
            ->toString(),
    ]);
}
```

## Custom Validation

**Use `after()` method for complex validation:**

```php
public function after(): array
{
    return [
        new ImageDimensionsValidator($this->file('image')),
    ];
}
```

**Custom methods on Form Request:**

```php
public function persist(): Post
{
    $post = Post::create($this->validated());
    $post->tags()->sync($this->tags ?? []);

    return $post;
}
```

Usage in controller:
```php
public function store(StorePostRequest $request): RedirectResponse
{
    $request->persist();
    msg_success(trans('controllers.post_controller.created'));
    return to_intended_route('posts.index');
}
```

## Controller Patterns

**Resource Controller:**

```php
class PostController extends Controller
{
    public function index(Request $request): View
    {
        $posts = Post::with(['author', 'category'])
            ->searchBy($request->search)
            ->filterByStatus($request->status)
            ->filterByCategory($request->category)
            ->orderBy(
                $request->get('order_by', 'created_at'),
                $request->get('order_direction', 'desc')
            )
            ->paginate(15);

        return view('posts.index', [
            'posts' => $posts,
            'categories' => Category::all(),
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        Gate::authorize('update', $post);
        $post->update($request->validated());
        msg_success(trans('controllers.post_controller.updated'));
        return to_intended_route('posts.index', urlSuffix: "#post-{$post->id}");
    }
}
```

**Admin Controller with User Creation:**

```php
public function store(StorePostRequest $request): RedirectResponse
{
    $attributes = $request->validated();

    if ($request->author_email !== null) {
        $user = User::firstOrCreate(
            ['email' => $request->author_email],
            [
                'password' => Hash::make(Str::random(16)),
                'role' => RoleEnum::Author,
            ]
        );

        $attributes['author_id'] = $user->id;

        if ($user->wasRecentlyCreated) {
            $user->sendWelcomeNotification();
        }
    }

    Post::create($attributes);
    msg_success(trans('controllers.post_controller.created'));
    return to_intended_route('admin.posts.index');
}
```

## Authorization

**Use Gates before updates:**

```php
Gate::authorize('update', $post);
Gate::authorize('delete', $post);
```

**Or policies:**

```php
$this->authorize('update', $post);
$this->authorize('delete', $post);
```

## Helper Functions

**App uses custom helpers:**

```php
msg_success(trans('controllers.post_controller.updated'));
msg_error(trans('controllers.post_controller.error'));

to_intended_route('posts.index', urlSuffix: "#post-{$post->id}")

trans('controllers.post_controller.updated')

order_direction($request)  // Flips asc/desc
```

## Routing

**Use named routes with `route()`:**

```php
return redirect()->route('posts.index');
return to_intended_route('posts.index');

$response->assertRedirect(route('posts.index'));
```

**Route registration in `routes/web.php`:**

```php
Route::middleware(['auth'])->group(function () {
    Route::resource('posts', PostController::class)->only(['index', 'update']);
    Route::resource('comments', CommentController::class);
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('posts', Admin\PostController::class);
    Route::resource('users', Admin\UserController::class);
});
```

## API Controllers

**Return JSON responses:**

```php
public function show(Post $post): JsonResponse
{
    return response()->json([
        'title' => $post->title,
        'content' => $post->content,
        'status' => $post->status->value,
    ]);
}
```

**Use API Resources for formatting:**

```php
return PostResource::collection($posts);
return new PostResource($post);
```

## View Data

**Pass as associative arrays:**

```php
return view('posts.index', [
    'posts' => $posts,
    'categories' => $categories,
    'oppositeDirection' => order_direction($request),
]);
```

## Query Objects Pattern

**For complex queries, use dedicated query classes:**

```php
// app/Queries/CountTokesQuery.php
class CountTokesQuery {
    public function run(?string $email = null): self {
        $query = Token::forBuyer($email)->toBase()->selectRaw(/* ... */);
        $this->results = collect($query->first());
        return $this;
    }

    public function toDTO(): CountToken {
        return new CountToken(
            $this->results['unallocated'],
            $this->results['bounced']
        );
    }
}

// Usage
$dto = (new CountTokesQuery())->run($email)->toDTO();
```

## Controller Organization

```
app/Http/Controllers/
├── Admin/              # Admin-only controllers

│   ├── TokenController.php
│   ├── LinkController.php
│   └── StatController.php
├── Api/                # API endpoints

│   ├── TestAccessController.php
│   └── QuestionnaireUrlController.php
├── Webhooks/           # External webhooks

│   ├── CompletedQuestionnaireController.php
│   └── CompletedOrderController.php
├── Accounts/           # User account management

│   ├── UserController.php
│   └── PasswordController.php
└── Auth/               # Authentication

    ├── LoginController.php
    └── ForgotPasswordController.php
```

## Key Reminders

- Always use Form Requests (not inline `$request->validate()`)
- Array-based validation rules: `['required', 'email']`
- Type hints on parameters and return types
- `prepareForValidation()` for data cleanup
- `after()` for custom validation
- Use Gates/policies before destructive operations
- Named routes with `route()`
- Eager load relationships in controllers
- Helper functions: `msg_success()`, `to_intended_route()`, `trans()`

=== .ai/database rules ===

# Database Conventions

> Database query patterns, migrations, and Eloquent usage

## Prefer Eloquent Over DB Facade

**Use Eloquent models and relationships:**

```php
// Good - Eloquent
$posts = Post::with(['author', 'tags'])
    ->published()
    ->searchBy($searchTerm)
    ->paginate(15);

// Bad - Raw DB facade (avoid)
$posts = DB::table('posts')
    ->join('users', 'posts.author_id', '=', 'users.id')
    ->where('posts.title', 'LIKE', "%{$searchTerm}%")
    ->get();
```

**Use `Model::query()` not `DB::`:**

```php
// Good
$count = Post::query()->where('status', StatusEnum::Published)->count();

// Bad
$count = DB::table('posts')->where('status', 'published')->count();
```

## DB Facade Usage

**Only use DB:: for transactions:**

```php
DB::transaction(function () use ($attributes) {
    $user = User::create($attributes);
    $user->posts()->create(['title' => 'Welcome Post']);
});

// Or manual transaction control
DB::beginTransaction();
try {
    $user = User::create($attributes);
    $user->posts()->create(['title' => 'Welcome Post']);
    DB::commit();
} catch (Exception $e) {
    DB::rollBack();
    throw $e;
}
```

## Query Scope Chaining

**Leverage model scopes for filtering:**

```php
$posts = Post::query()
    ->searchBy($request->search)
    ->filterByStatus($request->status)
    ->filterByCategory($request->category)
    ->filterByAuthor($request->author_id)
    ->filterByTag($request->tag)
    ->filterPublished($request->published_only)
    ->orderBy($request->get('order_by', 'created_at'), $request->get('order_direction', 'desc'))
    ->paginate(15);
```

Each scope defined in model:

```php
public function scopeSearchBy(Builder $query, ?string $searchTerm): Builder
{
    return $query->when($searchTerm, function ($query, $searchTerm) {
        return $query->where('title', 'LIKE', "%{$searchTerm}%")
            ->orWhere('content', 'LIKE', "%{$searchTerm}%");
    });
}
```

## Eager Loading (Prevent N+1)

**Always eager load relationships:**

```php
// Good - 2 queries
$posts = Post::with(['author', 'tags'])->get();
foreach ($posts as $post) {
    echo $post->author->name;  // No additional query
}

// Bad - N+1 queries
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->author->name;  // Query per post!
}
```

**Conditional eager loading:**

```php
$posts = Post::with(['author', 'category'])
    ->when($includeComments, fn($q) => $q->with('comments'))
    ->get();
```

## firstOrCreate Pattern

**Upsert operations:**

```php
$user = User::firstOrCreate(
    ['email' => $request->email],  // Search by
    [  // Create with (if not found)
        'password' => Hash::make(Str::random(16)),
        'role' => RoleEnum::User,
    ]
);

if ($user->wasRecentlyCreated) {
    $user->sendWelcomeNotification();
}
```

**Other upsert methods:**

```php
// Update or create
$user = User::updateOrCreate(
    ['email' => $email],
    ['name' => $name]
);

// First or new (doesn't save)
$user = User::firstOrNew(['email' => $email]);
```

## Custom Query Objects

**For complex queries:**

```php
// app/Queries/PostStatsQuery.php
class PostStatsQuery
{
    public function run(?int $authorId): self
    {
        $this->published = Post::query()
            ->when($authorId, fn($q) => $q->where('author_id', $authorId))
            ->where('status', StatusEnum::Published)
            ->count();

        return $this;
    }

    public function toDTO(): PostStatsDTO
    {
        return new PostStatsDTO(
            published: $this->published,
            draft: $this->draft,
        );
    }
}
```

Usage:
```php
$stats = (new PostStatsQuery)->run($request->author_id)->toDTO();
```

## Migrations

**When modifying columns, include ALL attributes:**

```php
// Wrong - loses existing attributes
Schema::table('posts', function (Blueprint $table) {
    $table->string('slug')->unique();  // Loses nullable, default, etc.
});

// Right - preserve all attributes
Schema::table('posts', function (Blueprint $table) {
    $table->string('slug', 255)->nullable()->unique()->change();
});
```

**Common patterns:**

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('slug')->unique();
    $table->text('content');
    $table->foreignId('author_id')->constrained('users');
    $table->foreignId('category_id')->nullable()->constrained();
    $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
    $table->json('metadata')->nullable();
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

**Foreign keys:**

```php
$table->foreignId('author_id')
    ->constrained('users')
    ->onDelete('cascade');

$table->foreignId('category_id')
    ->nullable()
    ->constrained()
    ->nullOnDelete();
```

## Factory-Generated Test Data

**Use factories in tests:**

```php
// In tests
$post = Post::factory()->create();
$user = User::factory()->admin()->create();
$posts = Post::factory()->count(5)->published()->create();
```

**Factory states:**

```php
// database/factories/PostFactory.php
public function published(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => StatusEnum::Published,
        'published_at' => now(),
    ]);
}

public function draft(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => StatusEnum::Draft,
        'published_at' => null,
    ]);
}
```

## Query Performance

**Use `when()` for conditional queries:**

```php
$posts = Post::query()
    ->when($searchTerm, function ($query, $searchTerm) {
        return $query->where('title', 'LIKE', "%{$searchTerm}%");
    })
    ->when($status, function ($query, $status) {
        return $query->where('status', $status);
    })
    ->get();
```

**Select specific columns when possible:**

```php
$users = User::select('id', 'email', 'name')->get();
```

**Chunk large datasets:**

```php
Post::chunk(100, function ($posts) {
    foreach ($posts as $post) {
        // Process post
    }
});
```

## JSON Column Queries

**Query JSON fields:**

```php
// Metadata is JSON column
Post::where('metadata->featured', true)->get();
Post::whereJsonContains('metadata->tags', 'laravel')->get();
```

**Update JSON fields:**

```php
$post->update([
    'metadata->views' => $newViewCount,
]);
```

## Relationships

**Define in models:**

```php
// One-to-many
public function posts(): HasMany
{
    return $this->hasMany(Post::class, 'author_id');
}

// Belongs to
public function author(): BelongsTo
{
    return $this->belongsTo(User::class, 'author_id');
}

// Many-to-many
public function tags(): BelongsToMany
{
    return $this->belongsToMany(Tag::class);
}

// Has one
public function profile(): HasOne
{
    return $this->hasOne(Profile::class);
}
```

**Query relationships:**

```php
// Load relationship
$user->posts;

// Query relationship
$user->posts()->where('status', StatusEnum::Published)->get();

// Count relationship
$user->posts()->count();

// Exists check
$user->posts()->exists();
```

## Database Configuration

**Testing uses SQLite in-memory:**

```xml
<!-- phpunit.xml -->
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

**Production uses MySQL/PostgreSQL** (check `.env`)

## Key Tables Example

Core tables pattern (reference):
- `users` - Users (coaches/admins) with soft deletes
- `tokens` - Main questionnaire tokens with soft deletes
- `links` - Link tracking
- `teams` - Teams with soft deletes
- `team_token` - Pivot table (teams ↔ tokens) with soft deletes
- `campaign_tokens` - Campaign tokens
- `stat_logs` - Statistics logging
- `jobs`, `failed_jobs`, `job_batches` - Queue system
- `password_resets` - Password reset tokens
- `pulse_*` - Laravel Pulse tables (monitoring)
- `activity_log` - Activity logging

## Pivot Tables with Soft Deletes

```php
// Many-to-many with soft deletes
$token->teams()->attach($teamId);
$token->teams()->detach($teamId);
$token->teams()->sync([$teamId1, $teamId2]);

// Pivot table has soft deletes
Schema::create('team_token', function (Blueprint $table) {
    $table->foreignId('team_id')->constrained();
    $table->foreignId('token_id')->constrained();
    $table->softDeletes();
});
```

## Key Reminders

- **Prefer Eloquent** over DB facade
- DB:: only for transactions
- Eager load with `with()` to prevent N+1
- Use query scopes for filtering
- `firstOrCreate()` for upsert patterns
- Custom query objects for complex queries
- Include ALL column attributes when modifying in migrations
- Use factories for test data
- Query JSON columns with `->` notation

=== .ai/formatting rules ===

# Code Formatting and Quality

> Pint and Rector usage guidelines

## Tools Available

- **Laravel Pint** - Code style formatter (configured via `pint.json`)
- **Rector** - Automated refactoring and code quality tool

## Important: Manual Execution Only

**Never run pint or rector automatically as part of your workflow**

The user will run these tools manually when needed.

## Laravel Pint Configuration

### Basic pint.json Structure

```json
{
    "preset": "laravel",
    "rules": {
        "array_syntax": {
            "syntax": "short"
        },
        "binary_operator_spaces": {
            "default": "single_space"
        },
        "blank_line_before_statement": {
            "statements": ["return"]
        },
        "class_attributes_separation": {
            "elements": {
                "method": "one",
                "property": "one"
            }
        },
        "concat_space": {
            "spacing": "none"
        },
        "no_unused_imports": true,
        "ordered_imports": {
            "sort_algorithm": "alpha"
        },
        "single_quote": true,
        "trailing_comma_in_multiline": {
            "elements": ["arrays", "arguments", "parameters"]
        }
    }
}
```

### Available Presets

| Preset | Description |
|--------|-------------|
| `laravel` | Laravel framework style (recommended) |
| `psr12` | PSR-12 coding standard |
| `symfony` | Symfony coding standard |
| `per` | PER coding style |

### Common Rules

**Spacing and Alignment:**
```json
{
    "binary_operator_spaces": {
        "default": "single_space",
        "operators": {
            "=>": "align_single_space_minimal"
        }
    }
}
```

**Import Organization:**
```json
{
    "ordered_imports": {
        "sort_algorithm": "alpha",
        "imports_order": ["class", "function", "const"]
    },
    "no_unused_imports": true
}
```

**Method Chaining:**
```json
{
    "method_chaining_indentation": true
}
```

### Excluding Files/Directories

```json
{
    "exclude": [
        "vendor",
        "storage",
        "bootstrap/cache"
    ],
    "notPath": [
        "src/Legacy/*"
    ]
}
```

## Rector Configuration

### Basic rector.php Structure

```php
<?php

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/app/Legacy',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
    ]);
```

### Common Rule Sets

| Set | Purpose |
|-----|---------|
| `LevelSetList::UP_TO_PHP_84` | PHP 8.4 features |
| `SetList::CODE_QUALITY` | General code improvements |
| `SetList::DEAD_CODE` | Remove unused code |
| `SetList::EARLY_RETURN` | Refactor to early returns |
| `SetList::TYPE_DECLARATION` | Add type hints |

## Running Tools

### Pint Commands

```bash

# Check without fixing

./vendor/bin/pint --test

# Fix all files

./vendor/bin/pint

# Fix specific file/directory

./vendor/bin/pint app/Models

# Show diff without applying

./vendor/bin/pint --test -v
```

### Rector Commands

```bash

# Dry run (preview changes)

./vendor/bin/rector process --dry-run

# Apply changes

./vendor/bin/rector process

# Process specific path

./vendor/bin/rector process app/Services
```

## Key Reminders

- Never run formatting tools automatically
- Use `laravel` preset as the base
- Check `pint.json` exists before assuming rules
- Run with `--test` first to preview changes
- Rector handles code structure; Pint handles style
- Exclude vendor/storage directories

=== .ai/foundation rules ===

# Foundation Rules

> Core conventions, directory structure, and reply style for Laravel applications

## Application Context

- **Stack:** PHP 8.4 + Laravel 12
- **Frontend:** Hotwire (Turbo + Stimulus)
- **Architecture:** Server-rendered Blade templates with minimal JavaScript

## Code Conventions

- Follow all existing code conventions used in this application
- When creating or editing a file, check sibling files for correct structure, approach, and naming
- Use descriptive names for variables and methods
  - Example: `isRegisteredForDiscounts()`, not `discount()`
- Check for existing components to reuse before writing new ones

## Directory Structure

- Stick to existing directory structure
- **Do not create new base folders without approval**
- Custom directories in use:
  - `/app/Actions/` - Action classes
  - `/app/Enums/` - Enum definitions
  - `/app/Services/` - Service layer
  - `/app/ValueObjects/` - Value object classes

## Application Dependencies

- Do not change application dependencies without approval
- Current stack is intentionally minimal and focused

## Documentation Files

- **Only create documentation files (*.md) if explicitly requested by the user**
- Do not proactively create README files or other documentation

## Reply Style

- Be concise in explanations
- Focus on what's important rather than explaining obvious details
- Output text directly (not via bash echo)
- Keep responses focused on the task at hand

## Key Reminders

- Check sibling files before creating new ones
- Use descriptive method/variable names
- No new folders without approval
- No dependency changes without approval
- Documentation only when requested

=== .ai/jobs rules ===

# Job & Queue Conventions

> Job classes, queue dispatching, and background processing patterns

## Location & Namespace

```
app/Jobs/
├── ProcessPaymentJob.php
├── SendWelcomeEmailJob.php
├── GenerateReportJob.php
├── Concerns/
│   ├── HasFailed.php
│   └── HasRateLimited.php
└── Import/
    └── ImportUsersJob.php
```

Namespace: `App\Jobs`

## Basic Job Structure

```php
<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    public function handle(): void
    {
        $this->user->notify(new WelcomeNotification);
    }
}
```

## Dispatching Jobs

**Basic dispatch:**
```php
SendWelcomeEmailJob::dispatch($user);
```

**Dispatch with delay:**
```php
SendWelcomeEmailJob::dispatch($user)->delay(now()->addMinutes(10));
```

**Dispatch to specific queue:**
```php
SendWelcomeEmailJob::dispatch($user)->onQueue('emails');
```

**Dispatch after response:**
```php
SendWelcomeEmailJob::dispatchAfterResponse($user);
```

**Dispatch synchronously (no queue):**
```php
SendWelcomeEmailJob::dispatchSync($user);
```

## Job Configuration

**Queue and connection:**
```php
class ProcessPaymentJob implements ShouldQueue
{
    public $queue = 'payments';
    public $connection = 'redis';
    public $tries = 3;
    public $maxExceptions = 2;
    public $timeout = 120;
    public $backoff = [60, 120, 300];  // Retry delays
}
```

**Or via methods:**
```php
public function __construct(public Order $order)
{
    $this->onQueue('payments');
    $this->onConnection('redis');
}
```

## Failure Handling

**Failed method:**
```php
public function failed(Throwable $exception): void
{
    Log::error('Job failed', [
        'job' => static::class,
        'user_id' => $this->user->id,
        'error' => $exception->getMessage(),
    ]);

    // Notify admin, cleanup, etc.
}
```

**Reusable failure trait:**
```php
// app/Jobs/Concerns/HasFailed.php
trait HasFailed
{
    public function failed(Throwable $exception): void
    {
        Log::error('Job failed', [
            'job' => static::class,
            'error' => $exception->getMessage(),
        ]);
    }
}

// Usage in job
class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HasFailed;
}
```

## Rate Limiting

**Using middleware:**
```php
use Illuminate\Queue\Middleware\RateLimited;

public function middleware(): array
{
    return [
        new RateLimited('api-requests'),
    ];
}
```

**Custom rate limit trait:**
```php
// app/Jobs/Concerns/HasRateLimited.php
trait HasRateLimited
{
    public function middleware(): array
    {
        return [
            new RateLimited('external-api'),
        ];
    }
}
```

**Configure rate limiter in AppServiceProvider:**
```php
RateLimiter::for('external-api', function (object $job) {
    return Limit::perMinute(60);
});
```

## HTTP Client in Jobs

**Making API calls:**
```php
public function handle(): void
{
    $response = Http::timeout(30)
        ->retry(3, 100)
        ->post('https://api.example.com/webhook', [
            'user_id' => $this->user->id,
            'event' => 'created',
        ]);

    if ($response->failed()) {
        throw new Exception('API call failed: '.$response->body());
    }
}
```

## Chained Jobs

**Dispatch job chain:**
```php
Bus::chain([
    new ProcessOrderJob($order),
    new SendOrderConfirmationJob($order),
    new UpdateInventoryJob($order),
])->dispatch();
```

**With catch callback:**
```php
Bus::chain([
    new ProcessOrderJob($order),
    new SendOrderConfirmationJob($order),
])->catch(function (Throwable $e) use ($order) {
    Log::error('Order chain failed', ['order' => $order->id]);
})->dispatch();
```

## Job Batches

**Create batch:**
```php
$batch = Bus::batch([
    new ImportUserJob($chunk1),
    new ImportUserJob($chunk2),
    new ImportUserJob($chunk3),
])->then(function (Batch $batch) {
    // All jobs completed
})->catch(function (Batch $batch, Throwable $e) {
    // First failure
})->finally(function (Batch $batch) {
    // Batch finished (success or failure)
})->dispatch();
```

**Check batch status:**
```php
$batch = Bus::findBatch($batchId);
$batch->progress();  // 0-100
$batch->finished();
$batch->cancelled();
```

## Dispatching Other Jobs

**From within a job:**
```php
public function handle(): void
{
    // Process main logic
    $this->processData();

    // Dispatch follow-up job
    SendNotificationJob::dispatch($this->user);
}
```

## Unique Jobs

**Prevent duplicate jobs:**
```php
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessPaymentJob implements ShouldQueue, ShouldBeUnique
{
    public function uniqueId(): string
    {
        return $this->order->id;
    }

    public $uniqueFor = 3600;  // 1 hour
}
```

## Testing Jobs

**Assert job dispatched:**
```php
Queue::fake();

// Trigger action
$user->register();

Queue::assertPushed(SendWelcomeEmailJob::class);
```

**Assert with callback:**
```php
Queue::assertPushed(function (SendWelcomeEmailJob $job) use ($user) {
    return $job->user->id === $user->id;
});
```

**Assert on specific queue:**
```php
Queue::assertPushedOn('emails', SendWelcomeEmailJob::class);
```

**Assert chained:**
```php
Queue::assertChained([
    ProcessOrderJob::class,
    SendOrderConfirmationJob::class,
]);
```

## Common Patterns

### Process with External API

```php
class SyncUserToExternalServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HasFailed, HasRateLimited;

    public $tries = 3;
    public $backoff = [60, 120];

    public function __construct(
        public User $user,
    ) {}

    public function handle(ExternalApiClient $client): void
    {
        $response = $client->syncUser($this->user);

        if ($response->failed()) {
            $this->release(60);  // Re-queue with delay
        }
    }
}
```

### Import with Progress

```php
class ImportUsersJob implements ShouldQueue, ShouldBeUnique
{
    public function handle(): void
    {
        $rows = $this->parseFile();

        foreach ($rows as $row) {
            ProcessUserRowJob::dispatch($row);
        }
    }
}
```

## Key Reminders

- Implement `ShouldQueue` for background processing
- Use traits for common concerns (HasFailed, HasRateLimited)
- Configure `$tries`, `$backoff`, `$timeout` appropriately
- Implement `failed()` method for error handling
- Use job chains for sequential operations
- Use batches for parallel operations with monitoring
- Test with `Queue::fake()` and assertions
- Use `ShouldBeUnique` to prevent duplicates

=== .ai/laravel-boost rules ===

# Laravel Boost MCP Server

> Tools and documentation search for Laravel applications

## Overview

Laravel Boost is an MCP server with powerful tools designed specifically for this application. Use them frequently.

## Artisan Commands

- Use the `list-artisan-commands` tool when calling Artisan commands
- Double-check available parameters before running commands

## Absolute URLs

- Use the `get-absolute-url` tool when sharing project URLs with the user
- Ensures correct scheme, domain/IP, and port

```bash

# Get absolute URL for a path

get-absolute-url --path="/dashboard"

# Get absolute URL for a named route

get-absolute-url --route="home"
```

## Tinker and Debugging

- Use the `tinker` tool to execute PHP for debugging or querying Eloquent models
- Use the `database-query` tool when you only need to read from the database

## Browser Logs

- Read browser logs, errors, and exceptions using the `browser-logs` tool
- Only recent browser logs are useful - ignore old logs

## Documentation Search (Critical)

- **ALWAYS use the `search-docs` tool before other approaches**
- This tool returns version-specific documentation for packages installed in this application
- Perfect for Laravel ecosystem packages: Laravel, Tailwind, Hotwire Turbo, etc.
- **Must search docs before making code changes** to ensure correct approach

### Search Syntax

Pass multiple queries at once for best results:

1. **Simple word searches** (auto-stemming)
   - `query="authentication"` finds "authenticate" and "auth"

2. **Multiple words** (AND logic)
   - `query="rate limit"` finds content with both "rate" AND "limit"

3. **Quoted phrases** (exact position)
   - `query="infinite scroll"` finds exact phrase with adjacent words

4. **Mixed queries**
   - `query="middleware 'rate limit'"` finds "middleware" AND exact phrase "rate limit"

5. **Multiple queries** (recommended)
   - `queries=["authentication", "middleware"]` finds ANY of these terms

### Search Best Practices

- Use multiple, broad, simple, topic-based queries to start
  - Example: `['rate limiting', 'routing rate limiting', 'routing']`
- **Do not add package names to queries** - package info is already shared
  - Use `"test resource table"`, not `"filament 4 test resource table"`
- Filter by packages if you know what you need:
  - `packages=["laravel/framework", "inertiajs/inertia-laravel"]`

## Key Reminders

- **Always search docs first** before making code changes
- Use multiple broad queries for better results
- Don't include package names in queries
- Use `tinker` for quick Eloquent debugging
- Check `browser-logs` for frontend errors
- Use `list-artisan-commands` before running artisan

=== .ai/laravel-core rules ===

# Laravel Core Patterns

> Laravel 12 conventions, Eloquent, validation, configuration actively used in SimpleTimer

## Do Things the Laravel Way

### Artisan Make Commands

- Use `php artisan make:` commands to create new files
- Use the `list-artisan-commands` tool to see available commands
- Pass `--no-interaction` to all Artisan commands
- Pass correct `--options` for desired behavior

```bash

# Create a controller

php artisan make:controller TimeEntryController --no-interaction

# Create a generic PHP class

php artisan make:class Actions/SyncHourlyRateAction --no-interaction
```

## Database and Eloquent

### Relationships

- Always use proper Eloquent relationship methods with return type hints
- Prefer relationship methods over raw queries or manual joins

```php
public function timeEntries(): HasMany
{
    return $this->hasMany(TimeEntry::class);
}

public function client(): BelongsTo
{
    return $this->belongsTo(Client::class);
}
```

### Query Best Practices

- Prefer `Model::query()` over `DB::`
- Generate code that leverages Laravel's ORM capabilities
- **Prevent N+1 query problems** by using eager loading

```php
// Good: Eager loading
$projects = Project::with('client', 'timeEntries')->get();

// Bad: N+1 queries
$projects = Project::all();
foreach ($projects as $project) {
    echo $project->client->name; // Lazy loads client each time
}
```

### Query Scopes

- Use query scopes for common filters
- This project uses scopes like: `completed()`, `forClient()`, `forProject()`, `betweenDates()`, `latestFirst()`

```php
// In TimeEntry model
public function scopeCompleted($query): void
{
    $query->whereNotNull('end_at');
}

// Usage
$entries = TimeEntry::completed()->latestFirst()->get();
```

## Model Creation

### Factories and Seeders

- When creating new models, create useful factories and seeders too
- Use the `list-artisan-commands` tool to check options for `php artisan make:model`

```bash

# Create model with migration, factory, and seeder

php artisan make:model Project --migration --factory --seed --no-interaction
```

### Model Casts

- Use the `casts()` method on models (Laravel 12 convention)
- Do not use the `$casts` property

```php
// Correct (Laravel 12)
protected function casts(): array
{
    return [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];
}

// Incorrect (old style)
protected $casts = [
    'start_at' => 'datetime',
];
```

## Controllers and Validation

### Form Request Classes

- Always create Form Request classes for validation
- Do not use inline validation in controllers
- Include both validation rules and custom error messages
- Check sibling Form Requests to see if the application uses array or string-based validation rules

```php
// Create Form Request
php artisan make:request StoreTimeEntryRequest --no-interaction

// In the Form Request
public function rules(): array
{
    return [
        'project_id' => ['required', 'exists:projects,id'],
        'start_at' => ['required', 'date'],
    ];
}
```

## Routing and URLs

### Named Routes

- When generating links, prefer named routes and the `route()` function
- Do not hardcode URLs

```php
// Good
<a href="{{ route('projects.show', $project) }}">View Project</a>

// Bad
<a href="/projects/{{ $project->id }}">View Project</a>
```

## Configuration

### Environment Variables

- Use environment variables **only in configuration files**
- Never use `env()` function directly outside of config files
- Always use `config('app.name')`, not `env('APP_NAME')`

```php
// config/app.php
'name' => env('APP_NAME', 'Laravel'),

// Anywhere in the app
$appName = config('app.name'); // Correct
$appName = env('APP_NAME');    // Incorrect
```

## Laravel 12 Structure

### Streamlined Architecture

- No `app/Console/Kernel.php` - use `bootstrap/app.php` or `routes/console.php`
- Commands auto-register from `app/Console/Commands/`
- No middleware files in `app/Http/Middleware/` by default
- `bootstrap/app.php` registers middleware, exceptions, and routing files
- `bootstrap/providers.php` contains application-specific service providers

### Console Commands

- Files in `app/Console/Commands/` are automatically available
- No manual registration required

## Database Migrations

### Modifying Columns

- When modifying a column, the migration must include all attributes previously defined
- Otherwise, they will be dropped and lost

```php
// Correct: Includes all previous attributes
Schema::table('time_entries', function (Blueprint $table) {
    $table->decimal('duration', 8, 2)->nullable()->change();
});
```

### Eager Loading Limits

- Laravel 12 allows limiting eagerly loaded records natively

```php
$projects = Project::with(['timeEntries' => function ($query) {
    $query->latest()->limit(10);
}])->get();
```

## Frontend Build Errors

### Vite Manifest Error

- If you see "Unable to locate file in Vite manifest" error:
  - Run `npm run build`, or
  - Ask user to run `npm run dev` or `composer run dev`

=== .ai/models rules ===

# Model Conventions

> Eloquent models with type hints, relationships, scopes, and PHPDoc annotations

## Location & Namespace

```
app/Models/
├── User.php
├── Post.php
├── Comment.php
├── Category.php
└── Tag.php
```

Namespace: `App\Models`

## Type Declarations

**All relationships require return type hints:**

```php
public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}

public function author(): BelongsTo
{
    return $this->belongsTo(User::class, 'author_id');
}

public function tags(): BelongsToMany
{
    return $this->belongsToMany(Tag::class);
}

public function profile(): HasOne
{
    return $this->hasOne(Profile::class);
}
```

**Methods require explicit return types:**

```php
public function isAdmin(): bool
{
    return $this->role === RoleEnum::Admin;
}

public function newEloquentBuilder($query): PostQueryBuilder
{
    return new PostQueryBuilder($query);
}
```

## PHPDoc Blocks

**Include property types and array shapes:**

```php
/**
 * @property int $id
 * @property string $title
 * @property string|null $excerpt
 * @property string $content
 * @property StatusEnum $status
 * @property Carbon|null $published_at
 * @property-read Collection<int, Comment> $comments
 * @property-read int|null $comments_count
 * @property-read string $formatted_date
 *
 * @method static Builder|Post published()
 * @method static Builder|Post searchBy($searchTerm)
 * @mixin \Eloquent
 */
class Post extends Model
{
    // ...
}
```

## Casts

**Use `casts()` method** (not `$casts` property):

```php
protected function casts(): array
{
    return [
        'id' => 'integer',
        'is_featured' => 'boolean',
        'status' => StatusEnum::class,
        'category' => CategoryEnum::class,
        'published_at' => 'datetime',
        'metadata' => 'array',
    ];
}
```

## Attributes (Accessors/Mutators)

**Use `Attribute::make()` with `get`/`set`:**

```php
protected function fullName(): Attribute
{
    return Attribute::make(
        get: function () {
            return $this->first_name.' '.$this->last_name;
        }
    );
}

protected function slug(): Attribute
{
    return Attribute::make(
        set: fn (string $value) => Str::slug($value),
    );
}
```

Usage: `$user->full_name` (snake_case attribute name)

## Query Scopes

**Named scope methods with Builder return type:**

```php
public function scopePublished(Builder $query): Builder
{
    return $query->whereNotNull('published_at')
        ->where('published_at', '<=', now());
}

public function scopeSearchBy(Builder $query, ?string $searchTerm): Builder
{
    return $query->when($searchTerm, function ($query, $searchTerm) {
        return $query->where('title', 'LIKE', "%{$searchTerm}%")
            ->orWhere('content', 'LIKE', "%{$searchTerm}%");
    });
}

public function scopeFilterByStatus(Builder $query, ?string $status): Builder
{
    return $query->when($status !== null, function ($query) use ($status) {
        return $query->where('status', StatusEnum::from($status));
    });
}
```

Usage:
```php
Post::published()->searchBy($request->search)->get()
Post::filterByStatus($request->status)->paginate(15)
```

## Custom Query Builders

**Override `newEloquentBuilder()`:**

```php
// In Post model
public function newEloquentBuilder($query): PostQueryBuilder
{
    return new PostQueryBuilder($query);
}
```

Then create `app/QueryBuilders/PostQueryBuilder.php`:

```php
class PostQueryBuilder extends Builder
{
    public function whereAuthor(User $user): self
    {
        return $this->where('author_id', $user->id);
    }

    public function filterByCategory(?string $category): self
    {
        return $this->when($category, function ($query, $category) {
            return $query->where('category', $category);
        });
    }
}
```

Usage: `Post::whereAuthor($user)->get()`

## Global Scopes

**Apply in `boot()` method:**

```php
protected static function boot()
{
    parent::boot();
    static::addGlobalScope(new PublishedScope);
}
```

Create scope in `app/Models/Scopes/PublishedScope.php`:

```php
class PublishedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNotNull('published_at');
    }
}
```

## Eager Loading

**Prevent N+1 queries with `with()`:**

```php
// Bad - N+1 queries
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->author->name;  // Query per post
}

// Good - 2 queries total
$posts = Post::with(['author', 'comments'])->get();
foreach ($posts as $post) {
    echo $post->author->name;  // No additional query
}
```

**In controllers:**

```php
$posts = Post::with(['author', 'tags'])
    ->published()
    ->searchBy($request->search)
    ->paginate(15);
```

## Traits

**Common traits used:**

```php
use HasFactory, Notifiable, SoftDeletes;
use HasSlug;  // Custom trait
```

## Enum Usage

**Enums in models:**

```php
use App\Enums\StatusEnum;
use App\Enums\CategoryEnum;
use App\Enums\RoleEnum;

protected function casts(): array
{
    return [
        'status' => StatusEnum::class,
        'category' => CategoryEnum::class,
        'role' => RoleEnum::class,
    ];
}
```

**Enum keys are TitleCase:**

```php
enum StatusEnum: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}

enum CategoryEnum: string
{
    case Technology = 'technology';
    case Business = 'business';
    case Lifestyle = 'lifestyle';
}
```

## Static Methods

**Class-level operations:**

```php
public static function createWithSlug(array $attributes): self
{
    $attributes['slug'] = Str::slug($attributes['title']);

    return static::create($attributes);
}
```

Usage: `Post::createWithSlug(['title' => 'My Post', ...])`

## firstOrCreate Pattern

**Create or retrieve existing:**

```php
$user = User::firstOrCreate(
    ['email' => $request->email],  // Search criteria
    [  // Default values if creating
        'password' => Hash::make(Str::random(16)),
        'role' => RoleEnum::User,
    ]
);

if ($user->wasRecentlyCreated) {
    $user->sendWelcomeNotification();
}
```

## Model Events

**Accessing in models:**

```php
protected static function booted()
{
    static::creating(function ($post) {
        $post->slug = Str::slug($post->title);
    });

    static::created(function ($post) {
        // After create
    });
}
```

## Key Reminders

- Return type hints on all relationships and methods
- Use `casts()` method (not `$casts` property)
- Eager load with `with()` to prevent N+1
- PHPDoc with property types and array shapes
- Scopes return `Builder` type
- Custom query builders for complex filtering
- `firstOrCreate()` for upsert patterns

=== .ai/php rules ===

# PHP Conventions

> PHP 8.4 type declarations, constructor promotion, and coding standards

## Type Declarations

- **Always use explicit return type declarations** for methods and functions
- **Always use appropriate PHP type hints** for method parameters
- Include nullable types where applicable (`?string`, `?int`, etc.)

```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    // ...
}
```

## Constructor Property Promotion

- Use PHP 8 constructor property promotion in `__construct()`
- Do not allow empty `__construct()` methods with zero parameters

```php
public function __construct(
    public GitHub $github,
    protected UserRepository $users,
) {
}
```

## Control Structures

- Always use curly braces for control structures
- Even if the body contains only one line

```php
// Correct
if ($condition) {
    return true;
}

// Incorrect
if ($condition)
    return true;
```

## Comments and Documentation

- Prefer PHPDoc blocks over inline comments
- Never use comments within code unless something is very complex
- Add useful array shape type definitions when appropriate

```php
/**
 * @param array{name: string, email: string, age: int} $userData
 * @return array{id: int, created_at: string}
 */
public function createUser(array $userData): array
{
    // ...
}
```

## Enums

- Keys in an Enum should be TitleCase
- Examples: `FavoritePerson`, `BestLake`, `Monthly`

```php
enum Currency: string
{
    case UnitedStatesDollar = 'USD';
    case Euro = 'EUR';
    case BritishPound = 'GBP';
}
```

=== .ai/testing rules ===

# Testing Conventions

PHPUnit-based testing with factories, custom assertions, and comprehensive coverage patterns.

## Framework

- **PHPUnit v12** (NOT Pest)
- Configuration: `phpunit.xml`
- Database: SQLite in-memory
- Isolation: `LazilyRefreshDatabase` trait

## Test Structure

```
tests/
├── Feature/     # 67 files - HTTP, controllers, webhooks, jobs

├── Unit/        # 145 files - models, services, DTOs, queries

└── TestCase.php # Base class with CreatesApplication, LazilyRefreshDatabase

```

**Feature tests** - HTTP requests, middleware, controllers
**Unit tests** - Business logic, models, services, jobs

Use `php artisan make:test <name>` for feature tests, `--unit` for unit tests.

## Running Tests

```bash

# All tests

php artisan test

# Specific file

php artisan test tests/Feature/Http/Controllers/TokenControllerTest.php

# Filter by test name (recommended after changes)

php artisan test --filter=testName
```

Never run `pint` or `rector` as part of workflow.

## Factory Patterns

**Use `$this->faker` in factories** (not `fake()`):

```php
// In TokenFactory.php
'code' => $this->faker->unique->regexify('[A-Z0-9]{6}'),
'email' => $this->faker->unique()->safeEmail(),
```

**Factory states** (heavily used):

```php
// Available states
Token::factory()->allocated()->create()
Token::factory()->underWay()->create()
Token::factory()->completed()->create()
Token::factory()->unique()->create()
Token::factory()->adminGenerated()->create()

// Chaining
Token::factory()
    ->completed()
    ->for(User::factory(), 'buyer')
    ->create(['code' => 'ABC123'])
```

States defined in `database/factories/TokenFactory.php`:
- `allocated()` - token assigned to user
- `underWay()` - questionnaire started
- `completed()` - questionnaire finished
- `unique()` - custom name set
- `adminGenerated()` - source is admin
- `adminGeneratedCustom()` - admin + custom name

## Custom Assertions

**Form Request Testing:**

```php
use Jcergolj\FormRequestAssertions\TestableFormRequest;

#[Test]
public function method_has_store_token_request(): void
{
    $this->actingAs(create_admin())
        ->post(route('admin.tokens.store'));

    $this->assertContainsFormRequest(StoreTokenRequest::class);
}
```

**Middleware Testing:**

```php
#[Test]
public function admin_middleware_is_applied(): void
{
    $response = $this->get(route('admin.tokens.create'));
    $response->assertMiddlewareIsApplied(Admin::class);
}
```

**Model Assertions:**

```php
use Jcergolj\AdditionalTestAssertionsForLaravel\Traits\HasModelAssertions;

#[Test]
public function cast_status_as_enum(): void
{
    $token = new Token;
    $this->assertSame(
        TokenStatusesEnum::class,
        $token->getCasts()['status']
    );
}
```

**Turbo Testing:**

```php
use HotwiredLaravel\TurboLaravel\Testing\InteractsWithTurbo;

$response->assertTurboStream();
```

## Test Helpers

Custom helper in `tests/Utilities/functions.php`:

```php
create_admin()  // Returns admin User instance
```

Usage:
```php
$this->actingAs(create_admin())->post(route('admin.tokens.store'), [...])
```

## Common Patterns

**Feature Test Structure:**

```php
#[Test]
public function admin_can_generate_tokens(): void
{
    $response = $this->actingAs(create_admin())
        ->from(route('admin.tokens.create'))
        ->post(route('admin.tokens.store'), [
            'number' => 3,
            'report_type' => ReportTypesEnum::Main->value,
            'language' => LanguagesEnum::English->value,
        ]);

    $response->assertFound()
        ->assertSessionHas('flash')
        ->assertRedirect(route('tokens.index'));

    $this->assertCount(3, Token::all());
}
```

**Unit Test with Mocking:**

```php
#[Test]
public function generate_tokens_with_unique_codes(): void
{
    $generator = $this->mock(RandomTokenCodeGenerator::class);
    $generator->shouldReceive('generate')
        ->twice()
        ->andReturn('ABCDEFG', 'BCDEFGH');

    Token::generate(['number' => 2, ...]);

    $this->assertCount(2, Token::get());
    $this->assertDatabaseHas('tokens', ['code' => 'ABCDEFG']);
}
```

**Faking Services:**

```php
Notification::fake()
Queue::fake()
Mail::fake()
Event::fake()

// Later assertions
Notification::assertSentTo($user, MyCustomWelcomeNotification::class);
Event::assertDispatched(QuestionnaireCompleted::class);
Queue::assertPushed(SendEmailJob::class);
```

**Job Testing:**

```php
protected function setUp(): void
{
    parent::setUp();
    $this->token = Token::factory()->underWay()->create(['code' => 'ABC123']);
    Event::fake();
}

#[Test]
public function mark_test_as_completed(): void
{
    QuestionnaireCompletionJob::dispatch($this->payload);

    $this->token = $this->token->fresh();
    $this->assertSame(TokenStatusesEnum::Completed, $this->token->status);
}
```

## Modern PHPUnit Attributes

Uses PHP 8 attributes (not docblocks):

```php
#[Test]  // Marks test method
public function it_does_something(): void { }

#[CoversClass(Token::class)]  // Specifies class coverage
#[CoversMethod(Token::class, 'generate')]  // Specifies method coverage
class TokenTest extends TestCase { }
```

## Time Travel

```php
$this->travel(1)->hour();
$timeInThePast = Carbon::now();
$this->travelBack();

$this->freezeSecond();  // Lock time for test
```

## Key Reminders

- **Never delete tests** without permission
- Use factories with states for test data
- `$this->faker` in factories, not `fake()`
- Run minimal tests with `--filter`
- Fake notifications/queues/events in feature tests
- Use `fresh()` to re-fetch models after updates

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.17
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- rector/rector (RECTOR) - v2

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `pest-testing` — Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, browser testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `developing-with-turbo-basics` — Basics of developing with Turbo Laravel. Activates when starting a new Turbo Laravel project; using dom_id, dom_class, turbo_stream(), or turbo_stream_view() helpers; working with Blade components like x-turbo::frame, x-turbo::stream, x-turbo::stream-from, or x-turbo::refreshes-with; using @domid, @domclass, @channel, or @turbonative directives; checking wantsTurboStream(), wasFromTurboFrame(), or wasFromHotwireNative() request macros; or when the user mentions Hotwire, Turbo, HTML over the wire, or partial page updates.
- `developing-with-turbo-drive` — Develops with Turbo Drive for SPA-like navigation. Activates when configuring page morphing with x-turbo::refreshes-with; working with data-turbo, data-turbo-track, data-turbo-permanent, or data-turbo-preload attributes; managing cache control with x-turbo::exempts-page-from-cache, x-turbo::exempts-page-from-preview, or x-turbo::page-requires-reload; enabling view transitions with x-turbo::page-view-transition; handling form redirects with TurboMiddleware; customizing the progress bar; or when the user mentions Turbo Drive, navigation, page morphing, prefetching, or asset tracking.
- `developing-with-turbo-frames` — Develops with Turbo Frames for scoped navigation and lazy loading. Activates when using the x-turbo::frame Blade component or turbo-frame HTML element; working with data-turbo-frame targeting, frame lazy loading via src attribute, or data-turbo-action for URL updates; detecting frame requests with wasFromTurboFrame(); using frame morphing with refresh=&quot;morph&quot;; or when the user mentions Turbo Frame, turbo frame, scoped navigation, inline editing, lazy loading frames, or breaking out of a frame with _top.
- `developing-with-turbo-streams` — Develops with Turbo Streams for partial page updates and real-time broadcasting. Activates when using turbo_stream() or turbo_stream_view() helpers; working with stream actions like append, prepend, replace, update, remove, before, after, or refresh; using the Broadcasts trait, broadcastAppend, broadcastPrepend, broadcastReplace, broadcastRemove, or broadcastRefresh methods; listening with x-turbo::stream-from; using the TurboStream facade for handmade broadcasts; combining multiple streams; or when the user mentions Turbo Stream, broadcasting, real-time updates, WebSocket streams, or partial page changes.
- `developing-with-turbo-tests` — Tests Turbo Laravel features in PHPUnit or Pest. Activates when using the InteractsWithTurbo trait; simulating requests with $this-&gt;turbo(), $this-&gt;fromTurboFrame(), or $this-&gt;hotwireNative(); asserting responses with assertTurboStream(), assertNotTurboStream(), assertRedirectRecede(), assertRedirectResume(), or assertRedirectRefresh(); faking broadcasts with TurboStream::fake(), assertBroadcasted(), assertNothingWasBroadcasted(), or assertBroadcastedTimes(); writing feature tests for Turbo Stream responses; or when the user mentions testing Turbo, testing broadcasts, or Turbo test assertions.
- `developing-with-fortify` — Laravel Fortify headless authentication backend development. Activate when implementing authentication features including login, registration, password reset, email verification, two-factor authentication (2FA/TOTP), profile updates, headless auth, authentication scaffolding, or auth guards in Laravel applications.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.

=== hotwired-laravel/turbo-laravel rules ===

## Turbo Laravel

- Turbo Laravel is a package that integrates Hotwire (and Turbo.js) with Laravel.
- It provides Blade components, helpers, and testing utilities to build modern, reactive applications with minimal JavaScript.
- It leverages Laravel's server-side rendering and routing capabilities to deliver a seamless user experience.
- It supports Turbo Frames, Turbo Streams, and real-time updates via broadcasting.
- It encourages progressive enhancement with Stimulus for JavaScript behavior.
- It simplifies form handling, validation, and redirects in Turbo contexts.
- It offers testing utilities to assert Turbo-specific behaviors in feature tests.
- It integrates with Hotwire Native for building native mobile apps using the same backend.
- IMPORTANT: Activate the `developing-with-turbo-basics` skill to know more about the Blade helpers and components provided by Turbo Laravel.
- IMPORTANT: Activate the `developing-with-turbo-drive` skill when starting out working on a feature.
- IMPORTANT: Activate the `developing-with-turbo-frames` skill when dealing with sub-resources or sections of the page that can be updated independently.
- IMPORTANT: Activate the `developing-with-turbo-streams` skill when you need real-time updates or dynamic content changes.
- IMPORTANT: Activate the `developing-with-turbo-tests` skill when you need to write tests for Turbo-specific behavior.

=== laravel/fortify rules ===

# Laravel Fortify

- Fortify is a headless authentication backend that provides authentication routes and controllers for Laravel applications.
- IMPORTANT: Always use the `search-docs` tool for detailed Laravel Fortify patterns and documentation.
- IMPORTANT: Activate `developing-with-fortify` skill when working with Fortify authentication features.

</laravel-boost-guidelines>
