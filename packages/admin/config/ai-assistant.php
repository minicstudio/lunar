<?php

use Minic\LaravelAiAssistant\Support\BuiltInTools;

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-register Filament plugin
    |--------------------------------------------------------------------------
    |
    | When true, the package registers the AI Assistant plugin with the
    | configured Filament panel automatically (no manual plugin registration).
    |
    | Lunar registers its own gated plugin via the Lunar admin panel, so this
    | must stay false to avoid registering the package's ungated pages/bubble.
    |
    */
    'auto_register_filament' => false,

    /*
    |--------------------------------------------------------------------------
    | Filament panel ID
    |--------------------------------------------------------------------------
    |
    | The panel id to attach the AI Assistant to (e.g. 'admin').
    |
    */
    'filament_panel_id' => env('AI_ASSISTANT_FILAMENT_PANEL_ID', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Queue connection and name
    |--------------------------------------------------------------------------
    |
    | Queue used for ProcessAiMessage job. Null uses default connection/name.
    |
    */
    'queue_connection' => env('AI_ASSISTANT_QUEUE_CONNECTION'),
    'queue_name' => env('AI_ASSISTANT_QUEUE_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Response poll interval (seconds)
    |--------------------------------------------------------------------------
    |
    | How often the chat UI polls for new messages while waiting for a response.
    | Lower values (e.g. 1) update the UI sooner after the job completes.
    |
    */
    'response_poll_interval' => (int) env('AI_ASSISTANT_RESPONSE_POLL_INTERVAL', 1),

    /*
    |--------------------------------------------------------------------------
    | Tool query limit
    |--------------------------------------------------------------------------
    |
    | Default limit for tool queries (e.g. list of tools in settings).
    |
    */
    'tool_query_limit' => (int) env('AI_ASSISTANT_TOOL_QUERY_LIMIT', 100),

    /*
    |--------------------------------------------------------------------------
    | Built-in package tools
    |--------------------------------------------------------------------------
    |
    | Class names resolved from the container and registered on boot. Remove a
    | class from this list to disable that tool without editing the service
    | provider. When publishing this config, merge new entries from the
    | package default on upgrade.
    |
    */
    'built_in_tools' => BuiltInTools::defaultClasses(),

    /*
    |--------------------------------------------------------------------------
    | Custom tools (config-based registration)
    |--------------------------------------------------------------------------
    |
    | Tool class names to register with the assistant. Each class must implement
    | Minic\LaravelAiAssistant\Contracts\ToolInterface. They are resolved from
    | the container and registered after built-in tools. Manual registration
    | via AiAssistant::registerTool() in AppServiceProvider still works.
    |
    | Example: [\App\Ai\Tools\MyTool::class]
    |
    */
    'tools' => [],

    /*
    |--------------------------------------------------------------------------
    | Publishable views
    |--------------------------------------------------------------------------
    |
    | When true, install command can publish views to resources/views/vendor.
    |
    */
    'publishable_views' => true,

    /*
    |--------------------------------------------------------------------------
    | Settings page authorization gate
    |--------------------------------------------------------------------------
    |
    | Gate ability to check before allowing access to the AI Assistant settings
    | page (e.g. 'manageAiAssistantSettings'). If null, only Filament panel
    | authentication is required.
    |
    | Lunar does not use this value: the bundled settings page is gated by the
    | `ai:manage-settings` Lunar staff permission, while the chat page and chat
    | bubble are gated by the `ai:chat` permission. Leave it null unless you
    | register the package's own pages directly.
    |
    */
    'settings_page_gate' => env('AI_ASSISTANT_SETTINGS_GATE'),

    /*
    |--------------------------------------------------------------------------
    | Maximum chat message length
    |--------------------------------------------------------------------------
    |
    | Maximum length (characters) for a single user message. 0 means no limit.
    | Enforcing a limit helps reduce prompt-injection surface and token abuse.
    |
    */
    'max_message_length' => (int) env('AI_ASSISTANT_MAX_MESSAGE_LENGTH', 0),

    /*
    |--------------------------------------------------------------------------
    | Inject backend context into prompts
    |--------------------------------------------------------------------------
    |
    | When true, the current backend page context (e.g. Filament resource, record)
    | is captured and prepended to the user message so the assistant can give
    | contextual help (e.g. "You're on the Orders list — here's how to…").
    |
    */
    'inject_backend_context' => env('AI_ASSISTANT_INJECT_BACKEND_CONTEXT', true),

    /*
    |--------------------------------------------------------------------------
    | Embed chat script path
    |--------------------------------------------------------------------------
    |
    | Public path to the JS embed widget script (relative to app URL).
    | Used when building the embed snippet for settings/custom UIs.
    |
    */
    'embed_script_path' => env('AI_ASSISTANT_EMBED_SCRIPT_PATH', 'vendor/laravel-ai-assistant/ai-assistant-embed.js'),

    /*
    |--------------------------------------------------------------------------
    | Embed chat API prefix
    |--------------------------------------------------------------------------
    |
    | URL prefix for embed chat API routes (config, history, message stream).
    | Example: 'ai-assistant/embed' => /ai-assistant/embed/chat/config
    |
    */
    'embed_api_prefix' => env('AI_ASSISTANT_EMBED_API_PREFIX', 'ai-assistant/embed'),

    /*
    |--------------------------------------------------------------------------
    | Chart.js script URL (for diagram tool rendering)
    |--------------------------------------------------------------------------
    |
    | URL to Chart.js UMD build. Lazy-loaded when a chart block is present.
    | Defaults to jsDelivr CDN. Set AI_ASSISTANT_CHART_JS_SRC to a local path
    | (e.g. vendor/laravel-ai-assistant/chart.umd.min.js) if you publish the asset.
    |
    */
    'diagram' => [
        'max_points' => 50,
        'allow_database_charts' => true,
    ],

    'chart_js_src' => env('AI_ASSISTANT_CHART_JS_SRC', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'),

    /*
    |--------------------------------------------------------------------------
    | Table lookup tool defaults
    |--------------------------------------------------------------------------
    |
    | Safe defaults for the built-in table lookup tool. Sensitive columns are
    | always excluded from query results. Limits are hard-capped.
    |
    */
    'table_lookup' => [
        'default_max_rows' => 50,
        'default_max_joins' => 3,
        'sensitive_columns' => [
            'password',
            'remember_token',
            'api_key',
            'secret',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'token',
            'access_token',
            'refresh_token',
        ],

        /*
        | Substrings blocked in complex_query SQL (case-insensitive), merged with
        | sensitive_columns. Empty array adds no extra entries beyond sensitive_columns.
        */
        'complex_query_blocked_sql_substrings' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider tools (SDK provider tools: web search, web fetch)
    |--------------------------------------------------------------------------
    |
    | Defines which AI providers support which provider tools. Used by the
    | Assistant to filter tools at runtime and by the settings page for the
    | enabled provider tools checkbox list. Update when Laravel AI SDK adds
    | support for new providers. Labels are used in the Filament settings UI.
    |
    | Structure: tool_key => ['providers' => ['openai', ...], 'label' => 'Label']
    |
    */
    'provider_tools' => [
        'web_search' => [
            'providers' => ['openai', 'anthropic', 'gemini'],
            'label' => 'Web Search',
        ],
        'web_fetch' => [
            'providers' => ['anthropic', 'gemini'],
            'label' => 'Web Fetch',
        ],
    ],

];
