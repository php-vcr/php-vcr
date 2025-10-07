# Hook Strategy Documentation

## Overview

PHP-VCR uses different interception strategies (hooks) to record and replay HTTP requests. This document clarifies when to use each approach.

## Hook Types

### 1. High-Level Hooks (Recommended)

**SymfonyHttpClientHook** - Decorator pattern for Symfony HttpClient

- **Use for**: Symfony\Component\HttpClient usage
- **How it works**: Wraps the HttpClient instance via `VCRHttpClient` decorator
- **Benefits**:
  - Clean separation of concerns
  - No timing issues with async requests
  - Proper handling of response streaming
  - Automatic gzip decompression
  - Works with all Symfony HttpClient implementations (CurlHttpClient, NativeHttpClient, etc.)

**Recommended setup**:
```php
use VCR\VCRHttpClient;
use Symfony\Component\HttpClient\CurlHttpClient;

$client = new VCRHttpClient(new CurlHttpClient());
```

### 2. Low-Level Hooks (Legacy Support)

**CurlHook** - Function interception for direct curl_* usage

- **Use for**: Direct calls to `curl_exec()`, `curl_multi_exec()`, etc.
- **How it works**: Intercepts curl function calls via code transformation
- **Limitations**:
  - Timing issues with modern async HTTP clients (see comments in CurlHook.php:80-95)
  - Complex state management with static properties
  - Fragile cleanup logic (potential memory leaks in tests)
  - Does not handle gzip decompression properly for some clients

**StreamWrapperHook** - Stream wrapper interception

- **Use for**: `file_get_contents()`, `fopen()` for HTTP
- **How it works**: Registers custom stream wrapper
- **Note**: Uses `$wrapper_data` public property for `stream_get_meta_data()` compatibility

## Recommendations

1. **For Symfony projects**: Always use `SymfonyHttpClientHook` / `VCRHttpClient`
2. **For Guzzle**: Use Guzzle's native handler support with VCR
3. **For legacy code**: `CurlHook` and `StreamWrapperHook` are maintained for backward compatibility

## Architecture Decision

The dual-hook architecture exists because:

- Modern HTTP clients (Symfony, Guzzle 6+) work better with decorator/handler patterns
- Low-level hooks are necessary for legacy code and direct curl usage
- Code transformation hooks have inherent fragility with complex async operations

**Future direction**: Prioritize high-level hooks for better maintainability and reliability.

## Related Code

- `VCR\VCRHttpClient` - Main Symfony HttpClient wrapper
- `VCR\LibraryHooks\SymfonyHttpClientHook` - Hook registration
- `VCR\LibraryHooks\CurlHook` - Low-level curl interception (legacy)
- `VCR\Videorecorder::enableLibraryHooks()` - Hook activation mechanism
