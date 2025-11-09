To use the ContactsEncoder module:

1. Extend the platform-specific ContactsEncoder class

2. Instantiate the encoder

```php
$contacts_encoder_params = new Params();
$contacts_encoder_params->api_key = 'CleanTalk API key';

$contacts_encoder = ContactsEncoder::getInstance($contacts_encoder_params);
```

3. Prepare the content (HTML, text, etc.)

4. Encode contact details

```php
$encoded_content = $contacts_encoder->runEncoding('your content do encode');
```

5. Output the modified content

```php
echo $encoded_content;
```
