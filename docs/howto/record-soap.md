# Record SOAP Requests

> One-liner: works with `SoapClient` (or your own subclass) exactly like any other hook — code just needs to
> be loaded via `require`/`include`, same as `curl`.

**On this page:** [Requirements](#requirements) · [Recording a SOAP call](#recording-a-soap-call) · [Matching SOAP requests](#matching-soap-requests)

## Requirements

The `soap` hook needs `ext-soap` and `ext-xml` — see [Requirements](../requirements.md#extensions). If either
is missing, constructing the hook throws `\BadMethodCallException` with a message telling you which extension
to install.

## Recording a SOAP call

```php
// soap_client.php — loaded via require, not called from the entry script directly
function greet_via_soap(string $wsdlUrl, string $name): string
{
    $client = new SoapClient($wsdlUrl, ['location' => 'http://api.example.com/soap']);
    return $client->greet($name);
}
```

```php
// test bootstrap / test method
\VCR\VCR::configure()->setCassettePath(__DIR__ . '/cassettes');
\VCR\VCR::turnOn();
require __DIR__ . '/soap_client.php';

\VCR\VCR::insertCassette('soap_demo');
echo greet_via_soap('http://api.example.com/wsdl', 'php-vcr'); // recorded first run, replayed after
\VCR\VCR::eject();
\VCR\VCR::turnOff();
```

Interception happens at `SoapClient::__doRequest()` — right before the SOAP envelope is sent — so any custom
logic in a class that `extends SoapClient` runs unaffected; only the outbound wire call is captured.

> **📌 Note:** `SoapClient`'s WSDL parsing has its own cache (`soap.wsdl_cache_enabled`, on by default) that's
> independent of php-vcr. Don't be surprised if a WSDL fetch still "works" with the network down on a
> subsequent run purely because of that cache — the thing php-vcr actually records and replays is the SOAP
> operation call itself, confirmed by killing the target server entirely and re-running successfully.

## Matching SOAP requests

The [`soap_operation`](../reference/request-matchers.md#soap_operation) matcher distinguishes between
different operations sent to the same endpoint (extracted from `<SOAP-ENV:Body><operation>` in the body) — it
returns `true` (i.e. doesn't block a match) for anything that isn't a SOAP body, so it's safe to leave enabled
even in a codebase that mixes SOAP with other HTTP calls.

---
← [Select library hooks](select-library-hooks.md) · Next: [VCR Facade reference](../reference/vcr-facade.md) →
