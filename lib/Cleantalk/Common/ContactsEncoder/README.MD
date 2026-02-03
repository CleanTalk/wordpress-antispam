# Contacts Encoder - Quick Start Guide

## Overview

The Contacts Encoder protects email addresses and phone numbers from spam bots. It requires **4 essential components** to work properly.

## 🚀 Complete Setup in 4 Steps

### Step 1: PHP Backend Setup

#### 1.1 Configure Parameters (Params)

```php
use Cleantalk\Common\ContactsEncoder\Dto\Params;

$params = new Params();
$params->api_key = 'your_cleantalk_api_key';  // REQUIRED for encryption
$params->obfuscation_mode = Params::OBFUSCATION_MODE_BLUR;
$params->do_encode_emails = true;
$params->do_encode_phones = true;
$params->is_logged_in = false;  // Set true for admin users
```
#### 1.2 Create Platform-Specific Class

```php
use Cleantalk\Common\ContactsEncoder\ContactsEncoder as BaseEncoder;

class YourPlatformContactsEncoder extends BaseEncoder
{
    // REQUIRED: Add request validation
    protected function checkRequest()
    {
        // Anti-Spam protection for $_POST here
        return true;
    }

    // OPTIONAL: Customize messages
    protected function getTooltip()
    {
        return 'Click to decode protected contact';
    }
}
```

### Step 2: Encoding Content

```php
// Initialize encoder
$encoder = YourPlatformContactsEncoder::getInstance($params);

// Process your content
$protectedHtml = $encoder->runEncoding($yourHtmlContent);

// Output to browser
echo $protectedHtml;
```

### Step 3: Frontend Assets

#### 3.1 Include CSS

```html
<link rel="stylesheet" href="/path/to/contacts-encoder.css">
```

#### 3.2 Include JavaScript

```html
<script src="/path/to/ContactsEncoder.js"></script>
```

### Step 4: JavaScript Configuration

#### 4.1 Create Config Object

```javascript
const encoderConfig = {
    // REQUIRED: AJAX endpoint
    decodeContactsRequest: (encodedNodes) => {
        return fetch('/your-ajax-endpoint', {
            method: 'POST',
            body: encodedNodes
        }).then(r => r.json());
    },

    // OPTIONAL: Custom texts
    texts: {
        waitForDecoding: 'Decoding contact...',
        gotIt: 'Continue',
        blocked: 'Access denied'
    },
    
    serviceData: {
        brandName: 'Your Site Name'
    }
};
```

### 4.2 Initialize on Frontend

```javascript
document.addEventListener('DOMContentLoaded', () => {
    new ContactsEncoder(encoderConfig);
});
```
