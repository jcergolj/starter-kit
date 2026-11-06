## Guidelines Priority
When conventions from `~/work/ai-framework/agent_docs/` conflict with the Boost guidelines below, the framework conventions take precedence. Boost provides package versions, skills, and tooling info; the framework provides coding style and architectural conventions.

===

<laravel-boost-guidelines>
=== .ai/controllers rules ===

# Controller & Form Request Conventions

> Controller patterns, Form Request validation, and routing conventions

Before changing controllers, read [validation and authorization standards](../skills/laravel-full-stack/references/coding-standards.md#validation-and-authorization) and [controller preferences](../skills/laravel-full-stack/references/coding-standards-preferences.md#controllers-and-user-facing-text). For the base request, array rules, enum validation, and conditional rules, read [Form Request patterns](../skills/laravel-full-stack/references/form-requests.md).

## Controller Location & Structure

For controller, request, and route placement, read [foundation.md](foundation.md#directory-structure). Namespaces in examples are illustrative; preserve the active project's layout.

## Type Hints Required

For declarations, follow [PHP standards](../skills/laravel-full-stack/references/coding-standards.md#php). Examples:

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

## Data Preparation

Example normalizing a slug and email:

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

These helpers are project-specific, not Laravel built-ins. Use them only when the active project defines them; otherwise use its available redirect, session, and translation APIs:

```php
msg_success(trans('controllers.post_controller.updated'));
msg_error(trans('controllers.post_controller.error'));

to_intended_route('posts.index', urlSuffix: "#post-{$post->id}")

trans('controllers.post_controller.updated')

order_direction($request)  // Flips asc/desc
```

## Routing

For named URL generation, follow [routing guidance](laravel-core.md#routing-and-urls). Examples:

```php
return redirect()->route('posts.index');
return to_intended_route('posts.index');

$response->assertRedirect(route('posts.index'));
```

Example route groups; register the file according to [foundation.md](foundation.md#directory-structure):

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

=== .ai/database rules ===

# Database Patterns

Before database changes, read [model and query standards](../skills/laravel-full-stack/references/coding-standards.md#models-and-queries). For atomic operations and migration policy, read [business logic](../skills/laravel-full-stack/references/coding-standards.md#business-logic-and-jobs) and [migration standards](../skills/laravel-full-stack/references/coding-standards.md#migrations). The examples below cover additional database patterns, not a separate set of conventions.

## Queries and relationships

For scopes, custom builders, and create-or-retrieve operations, read [model examples](models.md). For casts, relationships, and Attributes, read [model patterns](../skills/laravel-full-stack/references/models.md).

Select only the columns needed by the behavior, retaining keys required for hydration and relationship matching:

```php
$users = User::select('id', 'email', 'name')->get();
```

Treat sort fields and directions as a validated allowlist rather than passing arbitrary request values to `orderBy()`. Group alternative search conditions so an `orWhere()` cannot bypass an earlier ownership or visibility constraint:

```php
$posts = Post::where('author_id', $author->id)
    ->when($searchTerm, function (Builder $query, string $searchTerm): Builder {
        return $query->where(function (Builder $search) use ($searchTerm): void {
            $search->where('title', 'LIKE', "%{$searchTerm}%")
                ->orWhere('content', 'LIKE', "%{$searchTerm}%");
        });
    })
    ->orderBy('created_at', 'desc')
    ->paginate(15);
```

Imports in fragments follow the active project's classes; `Builder` here is `Illuminate\Database\Eloquent\Builder`.

## Query objects

Use a query object when the operation earns a reusable interface, following [codebase-design](../skills/codebase-design/SKILL.md). Keep results local so repeated calls cannot retain stale state:

```php
class PostStatsQuery
{
    public function run(int $authorId): PostStatsDTO
    {
        $published = Post::where('author_id', $authorId)
            ->where('status', StatusEnum::Published)
            ->count();

        $draft = Post::where('author_id', $authorId)
            ->where('status', StatusEnum::Draft)
            ->count();

        return new PostStatsDTO(published: $published, draft: $draft);
    }
}
```

`PostStatsDTO` is a project-defined result type. Resolve the query object according to [dependency preferences](../skills/laravel-full-stack/references/coding-standards-preferences.md#php-and-dependency-resolution). Apply the agreed authorization and tenant scope before reporting statistics.

## Large datasets

When processing mutates records used in filtering or ordering, consider ID-based chunking rather than offset-based chunks. Verify the chosen key is stable and selected:

```php
Post::where('status', StatusEnum::Draft)
    ->chunkById(100, function (Collection $posts): void {
        foreach ($posts as $post) {
            // Perform the agreed operation.
        }
    });
```

`Collection` here is `Illuminate\Database\Eloquent\Collection`. Choose chunk size and concurrency controls from the workload rather than treating the example value as a default.

## JSON columns

Check support against the project's actual database engine and Laravel version:

```php
Post::where('metadata->featured', true)->get();

Post::whereJsonContains('metadata->tags', 'laravel')->get();

$post->update(['metadata->views' => $newViewCount]);
```

Inspect model assignment rules before nested updates. A read-then-write counter can lose concurrent updates; use the project's atomic update approach when that behavior matters.

## Schema changes

For changing columns while retaining attributes, read [Laravel core migration guidance](laravel-core.md#database-migrations). For a complete create-table example, read [Eloquent model examples](../skills/laravel-full-stack/references/eloquent-model.md#migration-structure).

Choose foreign-key delete behavior from the domain's retention requirements. For example, a nullable category can survive category deletion:

```php
$table->foreignId('category_id')
    ->nullable()
    ->constrained()
    ->nullOnDelete();
```

Cascading an ownership foreign key can delete dependent records; agree that behavior before adding it.

## Pivot deletion semantics

Adding `deleted_at` to a pivot table does not make ordinary `attach()`, `detach()`, or `sync()` operations implement soft deletion. If association history must be retained, model it explicitly using the project's supported relationship design, and test removal, restoration, and duplicate-association behavior.

## Test data and database configuration

Read [Laravel test conventions](testing.md) for factories, isolation, saved-model assertions, and configuration discovery. For reusable states, read [factory state examples](../skills/laravel-full-stack/references/factory-states.md).

Discover the actual driver from configuration and the test runner. SQLite in-memory is an option, not a shared requirement; verify engine-specific behavior using the project's appropriate database. Read the schema and migrations for tables instead of treating a framework example as the active application's inventory.

=== .ai/formatting rules ===

# Formatting and Automated Quality Tools

Pint and Rector are run by the user unless the user or active project instructions explicitly request them as part of this task.

When execution is requested:

1. Read the project's Composer scripts, `pint.json`, and Rector configuration. Use the installed tools' `--help` to confirm supported commands and flags.
2. Select the configured check mode and scope it to the relevant change where possible.
3. Review the result before applying changes. Report the exact command and outcome.

Tooling configuration owns versions, presets, and rules; avoid copying another project's configuration into this reference. Code review focuses on behavior and standards that tooling does not already enforce.

=== .ai/foundation rules ===

# Laravel Foundation

Before implementing a Laravel change, read the active project's instructions, `composer.json`, frontend package manifest, relevant configuration, and sibling files. They determine versions, dependencies, directory layout, and the UI stack.

## Conditional references

- **Production PHP:** [shared coding standards](../skills/laravel-full-stack/references/coding-standards.md) and [additional preferences](../skills/laravel-full-stack/references/coding-standards-preferences.md).
- **Behavior changes:** [tdd](../skills/tdd/SKILL.md) for the loop and seams, plus [Laravel tests](testing.md) for framework conventions.
- **UI:** [frontend.md](frontend.md) to discover the stack and select its applicable branches.
- **Formatting:** [formatting.md](formatting.md) before running Pint or Rector.
- **Planning artifacts:** [planning-workflow.md](planning-workflow.md) when the user requests a plan or spec.

## Directory Structure

- Apply these defaults only where the active project has no explicit alternative; match its actual namespaces and registration mechanisms.
- Organize application behavior by feature under `/app/Features/<FeatureName>/`.
- Feature-owned controllers, requests, actions, jobs, mail, and notifications live in their corresponding feature directories. Shared integration controllers and requests remain under `app/Http/Controllers/` and `app/Http/Requests/`.
- Keep shared types and Laravel integration in conventional locations: `/app/Models/`, `/app/Enums/`, `/app/DataTransferObjects/`, `/app/Policies/`, `/app/Rules/`, `/app/ValueObjects/`, `/app/Console/`, and `/app/Providers/`.
- Feature route files live in `<Feature>/Routes/web.php` and are registered from the `then` callback of `bootstrap/app.php` with `Route::middleware('web')->group(...)`.
- Feature Blade directories are registered in `app/Providers/FeatureServiceProvider.php` and referenced with namespaces such as `settings::profile.edit`.
- Do not create a `Shared` feature or new top-level application folders for convenience.

## Project boundaries

Reuse the project's existing components and directory layout. Confirm changes to dependencies or top-level structure with the user when they are not already part of the agreed work. Create documentation when requested directly or as an output of an invoked workflow; link existing specs, plans, and decisions instead of duplicating them.

=== .ai/jobs rules ===

# Job & Queue Conventions

> Job classes, queue dispatching, and background processing patterns

Before changing a job, read [business logic and job standards](../skills/laravel-full-stack/references/coding-standards.md#business-logic-and-jobs). For placement, read [foundation](foundation.md#directory-structure); `App\Jobs` and shared paths below are illustrative. For test seams, fakes, and job execution, read [Laravel test conventions](testing.md#queues-mail-and-events). Verify queue configuration and installed APIs before adapting these examples.

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

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

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

These variants illustrate different execution contracts. Choose one from the required behavior, configured workers, and transaction boundaries; dispatch after response is not a substitute for a durable background worker. For supported transaction-aware dispatch, use `afterCommit()` when the job needs committed records.

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

**Retry and timeout configuration (structural fragment):**
```php
class ProcessPaymentJob implements ShouldQueue
{
    public int $tries = 3;
    public int $maxExceptions = 2;
    public int $timeout = 120;
    public array $backoff = [60, 120, 300];  // Retry delays
}
```

**Queue routing via the job's Queueable trait:**
```php
public function __construct(public Order $order)
{
    $this->onQueue('payments');
    $this->onConnection('redis');
}
```

## Failure Handling

Fragments below require imports for `Throwable` and the project's `Log` facade. Match the installed framework's `failed()` signature. A failed job may be re-instantiated for its failure callback, so rely on constructor inputs rather than state mutated in `handle()`.

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

Verify the limiter registration and installed middleware behavior. Rate-limit releases can consume attempts; coordinate `tries`, backoff, and any retry deadline so throttling does not exhaust the job before it runs. The reusable trait below is optional project code, not a Laravel built-in.

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
RateLimiter::for('external-api', function (object $job): Limit {
    return Limit::perMinute(60);
});
```

## HTTP Client in Jobs

Inject the project's integration client into `handle()` as shown in the external-service example below. For request/response wrappers and network fakes, read [HTTP client patterns](../skills/laravel-full-stack/references/http-client.md) and [framework fakes](testing.md#framework-fakes). Coordinate client-level retries with job retries; side effects such as payments require the integration's idempotency contract.

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
])->catch(function (Throwable $e) use ($order): void {
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
])->then(function (Batch $batch): void {
    // All jobs completed
})->catch(function (Batch $batch, Throwable $e): void {
    // First failure
})->finally(function (Batch $batch): void {
    // Batch finished (success or failure)
})->dispatch();
```

**Check batch status:**

Batch jobs need the installed framework's `Batchable` support. Decide how jobs honor cancellation, and handle a missing batch lookup rather than assuming every ID exists:
```php
$batch = Bus::findBatch($batchId);
if ($batch !== null) {
    $batch->progress();  // 0-100
    $batch->finished();
    $batch->cancelled();
}
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

**Unique enqueue lock (not business-level idempotency):**

Verify a shared lock-capable cache across dispatchers/workers. Uniqueness does not guarantee exactly-once side effects and may not apply to batch jobs in the installed framework. The fragment assumes the job carries an `Order $order` constructor input:
```php
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessPaymentJob implements ShouldQueue, ShouldBeUnique
{
    public function uniqueId(): string
    {
        return (string) $this->order->id;
    }

    public int $uniqueFor = 3600;  // Illustrative lock lifetime.
}
```

## Testing Jobs

Read [framework fakes](testing.md#framework-fakes) and [queue test conventions](testing.md#queues-mail-and-events) for selective faking, model/payload assertions, and executing the actual job. When queue routing is part of the contract, use the installed fake's `assertPushedOn()`; for chains or batches, choose assertions from the facade used to dispatch them. A dispatch assertion proves enqueueing, not successful worker execution.

## Common Patterns

### Process with External API

```php
class SyncUserToExternalServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HasFailed, HasRateLimited;

    public int $tries = 3;
    public array $backoff = [60, 120];

    public function __construct(
        public User $user,
    ) {}

    public function handle(ExternalApiClient $client): void
    {
        $response = $client->syncUser($this->user);

        if ($response->failed()) {
            throw new \RuntimeException('External user synchronization failed.');
        }
    }
}
```

### Import with Progress

Structural sketch only: supply domain inputs through the constructor, the dispatch/queue traits, and actual parsing logic. Implement `ShouldBeUnique` only when required and provide its stable `uniqueId()`. Follow the job standards rather than copying this incomplete class as runnable code:
```php
class ImportUsersJob implements ShouldQueue
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

## Verification

Use [tdd](../skills/tdd/SKILL.md) for the agreed behavior and completion checks. Select relevant retry, failure, cancellation, and idempotency scenarios from the operation's contract; report actual test and worker verification rather than inferring reliability from the job configuration.

=== .ai/laravel-boost rules ===

# Laravel Boost MCP Server

> Tools and documentation search for Laravel applications

## When to use

Read [AI Framework's Laravel standards](../skills/laravel-full-stack/references/coding-standards.md) first and follow their guidance precedence. Use Laravel Boost's recommendations and documentation search only for topics not covered by the active project's rules or framework references. Generated Boost guidelines and search results are fallback guidance, not replacements for the user's conventions.

When Boost is configured in the active project, its tools can verify runtime facts such as available commands, URLs, and logs. These lookups do not determine coding style. Use only tools actually exposed by the configured server; if unavailable, inspect the repository or primary documentation.

## Artisan Commands

- Use the `list-artisan-commands` tool when calling Artisan commands
- Double-check available parameters before running commands

## Absolute URLs

- Use the `get-absolute-url` tool when sharing project URLs with the user
- Ensures correct scheme, domain/IP, and port

Pass arguments using the configured tool's schema; MCP tool names are not shell commands.

## Tinker and Debugging

- Use the `tinker` tool to execute PHP for debugging or querying Eloquent models
- Use the `database-query` tool when you only need to read from the database

## Browser Logs

- Read browser logs, errors, and exceptions using the `browser-logs` tool
- Only recent browser logs are useful - ignore old logs

## Documentation search for uncovered topics

When the local guidance leaves a question open, use `search-docs` if available for version-specific documentation about installed packages. Apply results within the framework standards; a documentation example does not establish a new coding convention.

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

=== .ai/laravel-core rules ===

# Laravel Core Patterns

Discover the installed Laravel version and application layout through [foundation.md](foundation.md). Production conventions live in [coding standards](../skills/laravel-full-stack/references/coding-standards.md), not the examples below.

## Artisan commands

Use the installed `php artisan make:` commands for scaffolding. Inspect available options through `php artisan help <command>` or the configured Boost command-list tool. Use `--no-interaction` for unattended commands and choose the name and path according to the project's layout.

```bash
php artisan make:controller TimeEntryController --no-interaction
php artisan make:request StoreTimeEntryRequest --no-interaction
php artisan make:model Project --migration --factory --seed --no-interaction
```

For the generated model and request patterns, read [model scaffolding](../skills/laravel-full-stack/references/eloquent-model.md) or [Form Requests](../skills/laravel-full-stack/references/form-requests.md). Generate supporting factories or seeders only when the current slice needs them.

## Routing and URLs

Use named routes and `route()` for application links rather than hardcoding paths:

```blade
<a href="{{ route('projects.show', $project) }}">{{ __('View Project') }}</a>
```

For feature route registration and shared file placement, read [foundation.md](foundation.md#directory-structure).

## Configuration

Read environment variables in configuration files; use `config()` in application code so configuration caching works:

```php
// config/app.php
'name' => env('APP_NAME', 'Laravel'),

// Application code
$appName = config('app.name');
```

Inspect `bootstrap/app.php`, `bootstrap/providers.php`, `routes/console.php`, and command registration in the active project. Their presence and behavior depend on its Laravel version and any retained older structure; shared references do not authorize restructuring it.

## Database migrations

Follow [migration standards](../skills/laravel-full-stack/references/coding-standards.md#migrations). When modifying a column, include the existing attributes that must remain, after verifying the installed version's `change()` behavior:

```php
Schema::table('time_entries', function (Blueprint $table): void {
    $table->decimal('duration', 8, 2)->nullable()->change();
});
```

For query syntax and eager-loading examples, read [model patterns](models.md). Where supported, an eager-loading constraint can limit related rows:

```php
$projects = Project::with(['timeEntries' => function (Builder $query): void {
    $query->latest()->limit(10);
}])->get();
```

## Frontend build errors

For missing assets or a manifest error, follow [frontend discovery](frontend.md). Use the active project's actual scripts and bundler configuration rather than assuming Mix, Vite, or a build step.

=== .ai/models rules ===

# Model Conventions

> Eloquent models with type hints, relationships, scopes, and PHPDoc annotations

Before changing a model, read [coding standards](../skills/laravel-full-stack/references/coding-standards.md#models-and-queries). For base models, casts, relationships, PHPDoc, and Attribute examples, read [model patterns](../skills/laravel-full-stack/references/models.md). The examples below cover additional query and lifecycle patterns.

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

## PHPDoc Blocks

Example with virtual properties and static scope annotations:

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

## Query Scopes

Examples using the traditional scope API; check support in the installed Laravel version:

```php
public function scopePublished(Builder $query): Builder
{
    return $query->whereNotNull('published_at')
        ->where('published_at', '<=', now());
}

public function scopeSearchBy(Builder $query, ?string $searchTerm): Builder
{
    return $query->when($searchTerm, function (Builder $query, string $searchTerm): Builder {
        return $query->where('title', 'LIKE', "%{$searchTerm}%")
            ->orWhere('content', 'LIKE', "%{$searchTerm}%");
    });
}

public function scopeFilterByStatus(Builder $query, ?string $status): Builder
{
    return $query->when($status !== null, function (Builder $query) use ($status): Builder {
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

Place the builder in the active project's existing location for query builders:

```php
class PostQueryBuilder extends Builder
{
    public function whereAuthor(User $user): self
    {
        return $this->where('author_id', $user->id);
    }

    public function filterByCategory(?string $category): self
    {
        return $this->when($category, function (Builder $query, string $category): Builder {
            return $query->where('category', $category);
        });
    }
}
```

Usage: `Post::whereAuthor($user)->get()`

Match the installed parent's `newEloquentBuilder()` signature; keep an untyped parameter where typing it would narrow the inherited contract.

## Global Scopes

**Apply in `boot()` method:**

```php
protected static function boot(): void
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

For enum naming and transformations, read [value objects and enums](../skills/laravel-full-stack/references/coding-standards.md#value-objects-and-enums).

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
protected static function booted(): void
{
    static::creating(function (Post $post): void {
        $post->slug = Str::slug($post->title);
    });

    static::created(function (Post $post): void {
        // After create
    });
}
```

=== .ai/php rules ===

# PHP Conventions

Before writing or reviewing PHP, read [Laravel coding standards](../skills/laravel-full-stack/references/coding-standards.md#php) for strict types, declarations, constructor promotion, braces, and PHPDoc. For closures, extensibility, and dependency resolution, read [additional preferences](../skills/laravel-full-stack/references/coding-standards-preferences.md#php-and-dependency-resolution).

For enum case naming and domain transformations, read [value objects and enums](../skills/laravel-full-stack/references/coding-standards.md#value-objects-and-enums). Choose language features supported by the active project's PHP version.

=== .ai/testing rules ===

# Laravel Testing Conventions

This is the shared source of Laravel-specific test conventions. The test-first loop and seam selection live in [tdd](../skills/tdd/SKILL.md).

## Discover the project

Read `composer.json`, `phpunit.xml` (or its distribution file), `tests/TestCase.php`, and nearby tests before choosing syntax, database isolation, or helpers. Match the installed PHPUnit version and existing runner. Framework defaults use PHPUnit v13 (not Pest) with PHP attributes where supported.

Custom helpers and assertion traits below are project dependencies, not Laravel built-ins. Confirm they exist before using them; otherwise verify the same behavior through the project's available interface.

## Organization

- Controller tests: `Tests\Feature\Http\Controllers\...`.
- Command tests: `Tests\Feature\Commands\...`.
- Other seams, including jobs, listeners, models, actions, Form Requests, and Blade views: `Tests\Unit\...`, unless the project documents another layout. These tests may extend the application's `Tests\TestCase` when they need Laravel.
- Name test files after the class they cover (`UserControllerTest`). Use short, everyday language for methods: describe who does what and the result, rather than terms such as "eligibility" or "redisplays cross-language selections".
- Keep scenarios for the same controller action in its existing test class; merge overlapping classes rather than splitting tests by incidental scenarios such as bulk allocation. Group other tests by the responsibility they cover.
- Use existing model factory states. Use `$this->faker` in factories.
- Put shared model fixtures in `setUp()`; vary only the attributes relevant to the scenario in each test.
- Use the project's database reset trait and test database configuration.
- In the Arrange phase, separate each variable assignment from the next with a blank line.

## Observable assertions

- After exercising the agreed interface, find the saved model first, then use a separate `assertSame()` for each field relevant to the behavior. Scope the lookup to the intended record; use `first()` for a deliberately isolated single-record fixture or `firstOrFail()` when the record must exist. Prefer this field-by-field style over `assertDatabaseHas()` for saved values.
- For deletion or record counts, use the project's appropriate absence, soft-delete, or count assertion. Ask the user when the intended lookup, expected field values, or deletion behavior is unclear.
- Put a blank line between model lookup and assertions, and between separate assertions.
- Where the project supplies it, use `assertMiddlewareIsApplied()` to check middleware registration. For built-in middleware, a wiring assertion can replace repeated guest redirects or role checks wholly owned by that middleware. Keep HTTP coverage for application-specific authorization, tenant isolation, and distinct failure modes.
- Prefer the installed Laravel APIs over hand-written parsing and plumbing. For example, use `Uri` query access instead of manually splitting reset links when the installed version supports it.
- Prefer real application implementations. Fake only framework effects outside the behavior under test; mock external dependencies or a specifically agreed delegation seam.

Example after creating a user through the agreed interface:

```php
$user = User::where('email', 'alice@example.com')->firstOrFail();

$this->assertSame('Alice', $user->name);

$this->assertSame('alice@example.com', $user->email);
```

## Enum presentation

- Exercise every case of a user-facing enum through its label method, including translated output. Derive cases from `cases()` or check that an explicit label dataset covers all declared cases so additions cannot silently escape coverage.
- At an agreed UI seam, assert readable labels and the missing-value fallback. Keep persistence assertions separate so a display default does not silently replace stored `null` values.

## Validation ownership

- Controller tests assert the bound Form Request class using the project's assertion helper when available. Form Request unit tests own the validation rules and valid/invalid input cases.
- Keep controller validation cases only when they prove distinct HTTP behavior, such as preserving selected members after an error. Repeat the rule matrix only in the request tests.
- When consolidating tests, preserve distinct regression coverage and scenario-specific setup; a locale change may be what proves a notification keeps the selected language.

## Framework fakes

Use these examples only when the effect is outside the behavior under test. Confirm the helpers against the installed Laravel version and follow nearby tests.

- **Outbound HTTP:** use `Http::fake()` for the expected endpoint and `Http::preventStrayRequests()` to keep unmatched requests off the network. Run the application's real HTTP wrapper. Assert payloads with `Http::assertSent()` and a typed `Request` callback; use `assertSentCount()` for required counts. Inspect `Http::recorded()` only when the named assertions cannot express the check.
- **Mail:** use `Mail::fake()` when testing that the action sends or queues mail. Assert the intended mail, recipient, and relevant payload.
- **Queue or event dispatch:** use selective `Queue::fake([RelatedJob::class])` or `Event::fake([RelatedEvent::class])` for effects outside the test. Keep the job or listener being tested real.

Dispatch and delivery counts are valid assertions when the required behavior specifies the count, rather than an incidental internal call.

## Queues, mail, and events

Select fakes for the seam under test. Faking an event whose listener you need to exercise hides the behavior; faking the job being tested prevents its execution.

- Before the action, use `Queue::assertNothingPushed()`, `Mail::assertNothingOutgoing()`, or `Event::assertNotDispatched()` when proving no prior effect matters.
- To test that a caller dispatches a job, use `Queue::fake([ExpectedJob::class])` and `Queue::assertPushed()` with a closure that checks properties using `assertSame()` / `assertTrue()` and returns `true`. Compare models using `$job->model->is($expected)`.
- For queued mail, use `Mail::assertQueued()`; check `hasTo()`, custom properties such as `emailBody` and `emailSubject`, and return `true` from the assertion closure.
- For events, check `Event::assertDispatchedTimes(EventClass::class, 1)` and inspect the event with `Event::assertDispatched()` where the scenario requires exact count and payload.
- Listener tests use typed fixtures in `setUp()`, verify registration with `Event::assertListening()` when that is the agreed seam, and assert the listener's observable effects.
- To test a job's execution, follow nearby job tests: use `Job::dispatch()` with the project's synchronous test queue, keep that job real, and fake downstream jobs selectively. Verify the queue configuration before assuming dispatch executes immediately; `dispatchSync()` remains valid when immediate execution is explicitly needed.
- Let Laravel's queue handle job serialization and execution. A manual `serialize()` / `unserialize()` round trip is reserved for a serialization-specific regression, not a substitute for dispatch. Test signed-in and guest behavior as separate cases rather than pretending a direct `handle()` call is a background worker.
- Use the project's time-travel helpers for time-dependent behavior and restore time after the scenario.

## Hotreload middleware gotcha

When installed, `hotwired-laravel/hotreload` can convert View responses to HTML strings, breaking `assertViewHas()`, `assertViewIs()`, and `viewData()`. For tests needing those assertions, disable its actual middleware class in the project's test setup.

## Verification

Run the targeted test through the project's configured runner on each TDD cycle, then the relevant existing tests. State what ran, what passed, and any unresolved failures. Keep existing tests enabled; removing coverage requires explicit user agreement.

Formatting-tool policy lives in [formatting.md](formatting.md).

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

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

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== hotwired-laravel/turbo-laravel/core rules ===

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

=== tonysm/importmap-laravel/core rules ===

## Importmap Laravel

- Importmap Laravel lets you use ESM modules in Laravel without bundling or transpiling — no Webpack, Vite, npm, or yarn needed.
- **Do NOT use npm, yarn, or any Node.js package manager.** Use the `importmap:pin` Artisan command (or the `bin/importmap` wrapper) to add JavaScript dependencies. All pinned dependencies are downloaded to `resources/js/vendor/` and should be committed to version control.
- Dependencies are declared in `routes/importmap.php` using `Importmap::pin()` and `Importmap::pinAllFrom()`.
- Add `<x-importmap::tags />` inside the `<head>` tag of your layout to render the importmap, modulepreload links, and the entrypoint script.
- JSX (React) and Vue SFCs are NOT supported — they require a compilation step incompatible with importmaps.
- `process.env` is NOT available. Use `<meta>` tags and `document.head.querySelector('meta[name=...]').content` for JS configuration values.
- Version comments (e.g., `// @3.8.1`) on pin lines are used by `importmap:outdated` and `importmap:update` — do not remove them.
- IMPORTANT: When adding, removing, or updating JavaScript dependencies, or configuring the importmap for production, activate the `developing-with-importmap` skill for detailed guidance.

</laravel-boost-guidelines>
