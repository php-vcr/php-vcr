The apache integration test is here to test that Opcache does not have side-effects on PHP-VCR.

Especially, it tests that PHP-VCR is able to instrument a file that was previously loaded in cache via Opcache.