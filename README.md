# Navi PHP SDK

Official PHP SDK for the Navi AI Agent Platform.

## Requirements

- PHP 8.1 or higher
- Composer

## Installation

```bash
composer require navi-ai/php-sdk
```

Or add to your `composer.json`:

```json
{
    "require": {
        "navi-ai/php-sdk": "^1.0"
    }
}
```

## Quick Start

```php
<?php

require 'vendor/autoload.php';

use Navi\NaviClient;

// Initialize the client
$navi = new NaviClient('navi_sk_your_api_key', [
    'base_url' => 'https://your-navi-instance.com'
]);

// Create a conversation
$conversation = $navi->conversations->create([
    'agentId' => 'your-agent-uuid',
    'userId' => 'user-123',
    'title' => 'Support Request'
]);

// Send a message with streaming response
$navi->conversations->chat($conversation->id, 'Hello, I need help!', function($event) {
    if ($event->isTextDelta()) {
        echo $event->getText();
        flush();
    }
});

// Close the conversation when done
$navi->conversations->close($conversation->id);
```

## Configuration

```php
$navi = new NaviClient('navi_sk_your_api_key', [
    'base_url' => 'https://your-navi-instance.com',  // Required
    'api_path' => '/api/integration',                 // API path (default: /api/integration)
    'timeout' => 30,                                  // Request timeout in seconds
    'verify_ssl' => true,                             // SSL verification
]);
```

## API Reference

### Check API Status

```php
$status = $navi->status();

echo $status->integrationName;      // "My Integration"
echo $status->status;               // "active"
echo $status->rateLimitPerMinute;   // 60 or null
echo $status->defaultAgentId;       // "agent-uuid" or null
```

### Agents

#### List Available Agents

```php
$agents = $navi->agents->list([
    'search' => 'support',            // Optional: search by name
    'limit' => 20,                    // Optional: max results (default: 50)
    'offset' => 0                     // Optional: pagination offset
]);

foreach ($agents as $agent) {
    echo "{$agent->id}: {$agent->name}";
    if ($agent->isDefault) {
        echo " (default)";
    }
    echo "\n";
}
```

#### Get a Specific Agent

```php
$agent = $navi->agents->get('agent-uuid');

echo $agent->name;
echo $agent->description;
echo $agent->isDefault ? 'Default agent' : 'Not default';
```

### Conversations

#### Create a Conversation

```php
$conversation = $navi->conversations->create([
    'agentId' => 'agent-uuid',        // Required
    'userId' => 'user-123',           // Optional: identify the end user
    'userName' => 'John Doe',         // Optional: display name
    'title' => 'Order Inquiry',       // Optional: conversation title
    'context' => [                    // Optional: additional context
        'orderId' => '12345',
        'customerTier' => 'premium'
    ]
]);

echo $conversation->id;               // Conversation UUID
echo $conversation->status;           // "active"
```

#### List Conversations

```php
$conversations = $navi->conversations->list([
    'userId' => 'user-123',           // Optional: filter by user
    'status' => 'active',             // Optional: "active", "closed", "archived"
    'limit' => 20,                    // Optional: max results (default: 50)
    'offset' => 0                     // Optional: pagination offset
]);

foreach ($conversations as $conv) {
    echo "{$conv->id}: {$conv->title}\n";
}
```

#### Get a Conversation

```php
$conversation = $navi->conversations->get('conversation-uuid');

echo $conversation->title;
echo $conversation->status;
echo $conversation->messageCount;

// Messages are included
foreach ($conversation->messages as $message) {
    echo "[{$message->role}]: {$message->content}\n";
}
```

With user filtering (to ensure users only access their own conversations):

```php
$conversation = $navi->conversations->get('conversation-uuid', [
    'userId' => 'user-123'  // Only return if conversation belongs to this user
]);
```

#### Update a Conversation

```php
$conversation = $navi->conversations->update('conversation-uuid', [
    'title' => 'Updated Title'
]);

echo $conversation->title;  // "Updated Title"
```

With user filtering for authorization:

```php
$conversation = $navi->conversations->update('conversation-uuid',
    ['title' => 'New Title'],
    ['userId' => 'user-123']  // Only update if conversation belongs to this user
);
```

#### List Messages with Pagination

```php
$page = $navi->conversations->messages('conversation-uuid', [
    'userId' => 'user-123',  // Optional: filter by user for authorization
    'limit' => 20,
    'offset' => 0,
    'order' => 'desc'  // 'asc' (oldest first) or 'desc' (newest first)
]);

foreach ($page->messages as $message) {
    echo "[{$message->role}]: {$message->content}\n";
}

echo "Total: {$page->total}";
echo "Has more: " . ($page->hasMore ? 'yes' : 'no');

// Get next page
if ($page->hasMore) {
    $nextPage = $navi->conversations->messages('conversation-uuid', [
        'userId' => 'user-123',
        'limit' => 20,
        'offset' => $page->getNextOffset()
    ]);
}
```

#### Close a Conversation

```php
$navi->conversations->close('conversation-uuid');

// With user filtering for authorization
$navi->conversations->close('conversation-uuid', [
    'userId' => 'user-123'  // Only close if conversation belongs to this user
]);
```

### Chat

#### Streaming with Callback

Best for real-time output in CLI or streaming HTTP responses:

```php
$navi->conversations->chat($conversationId, 'Hello!', function($event) {
    match($event->type) {
        'message_created' => null, // User message saved
        'reasoning_delta' => null, // Agent thinking (optional to display)
        'tool_start' => print("Using tool: {$event->getToolName()}...\n"),
        'tool_complete' => print("Tool completed\n"),
        'response_delta' => print($event->getText()),
        'complete' => print("\n[Done]\n"),
        'error' => print("Error: {$event->getError()}\n"),
        default => null
    };
});
```

#### Streaming with Generator

For more control over the stream processing:

```php
$fullResponse = '';

foreach ($navi->conversations->chatStream($conversationId, 'Hello!') as $event) {
    if ($event->type === 'response_delta') {
        $fullResponse .= $event->getText();
        echo $event->getText();
    }

    if ($event->isError()) {
        throw new Exception($event->getError());
    }
}
```

#### Synchronous (Non-Streaming)

Wait for the complete response:

```php
$response = $navi->conversations->chatSync($conversationId, 'Hello!');

if ($response->success) {
    echo $response->content;
    echo "Tokens used: {$response->tokensUsed}";
    echo "Duration: {$response->durationMs}ms";
} else {
    echo "Error: {$response->error}";
}
```

#### With User Identification

You can specify the user sending the message. This creates or retrieves a contact to associate with the message:

```php
$navi->conversations->chat($conversationId, 'Hello!',
    function($event) { /* ... */ },
    [
        'userId' => 'user-123',       // Identifier for the end user
        'userName' => 'John Doe'      // Display name (used for new contacts or updates)
    ]
);
```

This is useful when:
- Multiple users share the same conversation (e.g., group support)
- You want to track which user sent each message
- You need to associate messages with specific contacts in the system

#### With Context

Pass additional context with your message:

```php
$navi->conversations->chat($conversationId, 'What is the status of my order?',
    function($event) { /* ... */ },
    [
        'context' => [
            'orderId' => '12345',
            'orderStatus' => 'shipped',
            'trackingNumber' => 'ABC123'
        ]
    ]
);
```

#### With Runtime Parameters

Runtime parameters are dynamic values that can be injected at execution time and referenced
in agent configurations using `${params.key}` syntax. Use them for user tokens, API keys,
and other per-execution values:

```php
$navi->conversations->chat($conversationId, 'Get my account balance',
    function($event) { /* ... */ },
    [
        'runtimeParams' => [
            'user_token' => 'jwt-abc123xyz',      // Used in: ${params.user_token}
            'api_key' => 'sk-secret-key',         // Used in: ${params.api_key}
            'user_id' => 42,                      // Used in: ${params.user_id}
            'organization_id' => 'org-123'        // Used in: ${params.organization_id}
        ]
    ]
);
```

**Where runtime parameters can be used in agent configuration:**

- MCP server headers: `Authorization: Bearer ${params.user_token}`
- HTTP request headers: `X-API-Key: ${params.api_key}`
- Custom functions: `tools.getParam('user_id')`
- Agent instructions: `You are helping user ${params.user_id}`

**Built-in parameters (automatically available):**

| Parameter | Description |
|-----------|-------------|
| `params.current_date` | Current date (YYYY-MM-DD) |
| `params.current_datetime` | Full ISO datetime |
| `params.current_timestamp` | Unix timestamp in ms |
| `params.execution_id` | Unique execution identifier |

Example combining user identification, context, and runtime parameters:

```php
$navi->conversations->chat($conversationId, 'Check my order status',
    function($event) {
        if ($event->isTextDelta()) {
            echo $event->getText();
        }
    },
    [
        'userId' => $_SESSION['user_id'],
        'userName' => $_SESSION['user_name'],
        'context' => [
            'orderId' => '12345'
        ],
        'runtimeParams' => [
            'user_token' => $_SESSION['user_token'],
            'user_id' => $_SESSION['user_id']
        ]
    ]
);
```

## Stream Events

| Event | Description | Data |
|-------|-------------|------|
| `message_created` | User message saved | `userMessageId`, `conversationId` |
| `reasoning_start` | Agent started thinking | - |
| `reasoning_delta` | Agent thinking text | `text` |
| `reasoning_complete` | Agent finished thinking | `action`, `confidence` |
| `tool_start` | Tool execution started | `tool`, `args` |
| `tool_complete` | Tool execution finished | `tool`, `result` |
| `response_start` | Response generation started | - |
| `response_delta` | Response text chunk | `text` |
| `response_complete` | Response finished | `response` |
| `complete` | Execution complete | `success`, `executionId`, `assistantMessageId`, `stats` |
| `error` | Error occurred | `error` |

## Error Handling

```php
use Navi\Exceptions\AuthenticationException;
use Navi\Exceptions\NotFoundException;
use Navi\Exceptions\RateLimitException;
use Navi\Exceptions\ValidationException;
use Navi\Exceptions\NaviException;

try {
    $conversation = $navi->conversations->get('invalid-uuid');
} catch (AuthenticationException $e) {
    // Invalid API key
    echo "Auth error: " . $e->getMessage();
} catch (NotFoundException $e) {
    // Resource not found
    echo "Not found: " . $e->getMessage();
} catch (RateLimitException $e) {
    // Rate limit exceeded
    echo "Rate limited. Retry after: " . $e->getRetryAfter() . " seconds";
} catch (ValidationException $e) {
    // Validation error
    echo "Validation error: " . $e->getMessage();
    print_r($e->getErrors());
} catch (NaviException $e) {
    // Other API error
    echo "Error: " . $e->getMessage();
    echo "Status: " . $e->getStatusCode();
}
```

## Complete Example

```php
<?php

require 'vendor/autoload.php';

use Navi\NaviClient;
use Navi\Exceptions\NaviException;

$navi = new NaviClient('navi_sk_your_api_key', [
    'base_url' => 'https://your-navi-instance.com'
]);

// Simulated user session
$currentUserId = 'customer-456';
$currentUserName = 'Jane Smith';

try {
    // Check API status
    $status = $navi->status();
    if (!$status->isActive()) {
        die("Integration is disabled");
    }

    // Create or get existing conversation
    $conversation = $navi->conversations->create([
        'agentId' => $status->defaultAgentId ?? 'your-agent-uuid',
        'userId' => $currentUserId,
        'userName' => $currentUserName,
        'title' => 'Product Inquiry'
    ]);

    echo "Conversation started: {$conversation->id}\n\n";

    // Chat loop
    while (true) {
        echo "You: ";
        $input = trim(fgets(STDIN));

        if ($input === 'quit' || $input === 'exit') {
            break;
        }

        echo "Agent: ";
        $navi->conversations->chat($conversation->id, $input,
            function($event) {
                if ($event->type === 'response_delta') {
                    echo $event->getText();
                }
            },
            [
                'userId' => $currentUserId,
                'userName' => $currentUserName
            ]
        );
        echo "\n\n";
    }

    // Close conversation (with user verification)
    $navi->conversations->close($conversation->id, [
        'userId' => $currentUserId
    ]);
    echo "Conversation closed.\n";

} catch (NaviException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
```

## License

MIT
