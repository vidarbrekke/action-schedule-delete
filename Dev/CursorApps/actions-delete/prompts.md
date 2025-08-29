# Action Scheduler Cleanup - Development Prompts

## Core Development Philosophy
- **DRY & YAGNI**: Only generalize when patterns repeat; avoid speculative abstractions
- **Single Responsibility**: Each unit does one thing - easier to test and maintain
- **WordPress Best Practices**: Follow WP coding standards and security guidelines
- **Production Ready**: Always consider error handling, logging, and performance

## Key Patterns Used
### Error Handling
```php
try {
    // Risky operation
} catch (Exception $e) {
    error_log('[Plugin] Operation failed: ' . $e->getMessage());
    return ['success' => false, 'error' => $e->getMessage()];
}
```

### Database Operations
- Transaction wrapping for atomicity
- Batch processing with LIMIT clauses
- Table existence validation
- LEFT JOIN optimization over subqueries

### Security
- Nonce verification on AJAX calls
- Capability checks (`manage_options`)
- SQL injection prevention with `$wpdb->prepare()`

## Testing Strategy
- Unit tests for each method
- Mock external dependencies
- Edge case and error condition coverage
- Integration testing for workflows

## Performance Optimization
- EXPLAIN ANALYZE for query optimization
- Batch operations for large datasets
- Proper indexing considerations
- Memory-efficient processing

## Deployment Checklist
- [ ] Code review completed
- [ ] Unit tests passing
- [ ] Manual testing in staging
- [ ] Database backup taken
- [ ] Rollback plan documented
