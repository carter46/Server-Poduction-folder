Place `firebase-service-account.json` here (never commit).

In `config.php`:

```php
define('FCM_PROJECT_ID', 'your-project-id');
define('FCM_SERVICE_ACCOUNT_JSON', __DIR__ . '/secrets/firebase-service-account.json');
```
