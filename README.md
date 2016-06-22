# Gdc Encryption Bundle

Install

```shell
composer require gusdecool/encryption-bundle
```

Add the bundle in `AppKernel.php`

```php
new Gdc\EncryptionBundle\GdcEncryptionBundle()
```

add config 

```yml
gdc_encryption:
    aes_key: "your-key-here"
```
