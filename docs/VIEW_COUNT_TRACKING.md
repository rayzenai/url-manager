# View Count Tracking

The URL Manager package now supports automatic view count tracking for models that use the `HasUrl` trait.

## How It Works

When a URL is visited, the `RecordUrlVisit` job is dispatched which:
1. Increments the URL's visit count
2. Updates the URL's last_visited_at timestamp
3. Checks if the model has a `getViewCountColumn()` method
4. If the method exists and returns a column name, increments that column

## Implementation

To enable view count tracking for your model:

### 1. Add the view count column to your database

```php
Schema::table('your_table', function (Blueprint $table) {
    $table->integer('view_count')->default(0);
});
```

### 2. Implement the `getViewCountColumn()` method in your model

```php
class Entity extends Model
{
    use \RayzenAI\UrlManager\Traits\HasUrl;
    
    /**
     * Get the view count column name for URL visit tracking
     */
    public function getViewCountColumn(): ?string
    {
        return 'view_count'; // Return the column name
    }
}
```

If your model doesn't track view counts, return `null`:

```php
public function getViewCountColumn(): ?string
{
    return null; // No view count tracking
}
```

## Benefits

This approach avoids expensive `Schema::hasColumn()` checks on every request by using a simple method check instead. Models explicitly declare their view count column, making the system more efficient and predictable.

## Custom Visit Tracking

For more advanced tracking needs, you can also implement a `recordVisit()` method in your model:

```php
public function recordVisit(?int $userId, array $metadata = []): void
{
    // Custom visit tracking logic
    // e.g., track unique visitors, geographic data, etc.
}
```

This method will be called automatically by the `RecordUrlVisit` job if it exists on your model.