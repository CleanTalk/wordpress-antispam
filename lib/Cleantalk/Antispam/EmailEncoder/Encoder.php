<?php

namespace Cleantalk\Antispam\EmailEncoder;

class Encoder
{
    private $cipher_algo  = "AES-128-CBC";
    private $secret_key;
    private $encrypted_string_splitter;
    private $encryption_is_available;
    private $use_ssl = true;

    public function __construct($api_key)
    {
        $this->secret_key = md5($api_key);
        $this->encrypted_string_splitter = substr($this->secret_key, 0, 3);
        $this->encryption_is_available = function_exists('openssl_encrypt') &&
                                         function_exists('openssl_decrypt') &&
                                         function_exists('openssl_cipher_iv_length') &&
                                         function_exists('openssl_random_pseudo_bytes') &&
                                         !empty($this->encrypted_string_splitter) && strlen($this->encrypted_string_splitter) === 3;
    }

    /**
     * @param bool $use_ssl
     *
     * @return void
     */
    public function useSSL($use_ssl)
    {
        $this->use_ssl = $use_ssl;
    }


    /**
     * Decoding previously encoded string
     *
     * @param $encoded_string string
     *
     * @return string
     */
    public function decodeString($encoded_string)
    {
        if ( $this->use_ssl && $this->encryption_is_available ) {
            $decoded_string = htmlspecialchars_decode($this->openSSLDecrypt($encoded_string));
        } else {
            $decoded_string = htmlspecialchars_decode(base64_decode($encoded_string));
            $decoded_string = str_rot13($decoded_string);
        }
        return $decoded_string;
    }

    /**
     * Encoding any string
     *
     * @param $plain_string string
     *
     * @return string
     */
    public function encodeString($plain_string)
    {
        global $apbct;
        try {
            if ( $this->use_ssl && $this->encryption_is_available ) {
                $encoded_email = htmlspecialchars($this->openSSLEncrypt($plain_string));
            } else {
                $encoded_email = htmlspecialchars(base64_encode(str_rot13($plain_string)));
            }
        } catch (\Exception $e) {
            $get_last_error = error_get_last();
            $get_last_error = isset($get_last_error['message']) ? $get_last_error['message'] : 'NO_ERROR';
            $variable = is_string($plain_string) ? $plain_string : 'TYPE_' . gettype($plain_string);
            $variable = '' === $variable ? 'EMPTY_STRING' : $variable;
            $action = !empty(current_action()) ? current_action() : 'NO_ACTION';
            $details = sprintf(
                '%s, last PHP error: [%s], hook: [%s], input var: [%s]',
                esc_html($e->getMessage()),
                esc_html($get_last_error),
                esc_html($action),
                esc_html($variable)
            );
            $apbct->errorAdd('email_encoder', $details);
            return $plain_string;
        }

        return $encoded_email;
    }

    /**
     * Encrypts a given plain string using the AES-128-CBC cipher algorithm and returns the encoded string.
     *
     * @param string $plain_string The plain text string that needs to be encrypted.
     * @return string The encrypted string, which is a combination of the base64-encoded initialization vector (IV) and the encrypted data, separated by a predefined splitter.
     * @throws \Exception If the input is not a string, is empty, or if any encryption step fails.
     */
    private function openSSLEncrypt($plain_string)
    {
        if (is_string($plain_string) && empty($plain_string)) {
            throw new \Exception('Empty plain string');
        }
        if (!is_string($plain_string)) {
            throw new \Exception('Plain variable is not string');
        }
        // Determine the length of the IV required for the AES-128-CBC cipher algorithm
        $iv_length = openssl_cipher_iv_length($this->cipher_algo);
        if ($iv_length === false) {
            throw new \Exception('Can\'t generate initializing vector length');
        }
        // Generate a random IV of the required length
        $iv = openssl_random_pseudo_bytes($iv_length);
        if (empty($iv)) {
            throw new \Exception('Can\'t generate initializing vector body');
        }
        // Encrypt the plain string using the specified cipher algorithm, secret key, and IV
        $encoded_string = @openssl_encrypt($plain_string, $this->cipher_algo, $this->secret_key, 0, $iv);
        if (empty($encoded_string)) {
            throw new \Exception('Can\'t encode plain string');
        }
        if (!function_exists('base64_encode')) {
            throw new \Exception('Can\'t run base64_encode');
        }
        // Base64-encode the IV and concatenate it with the encrypted string, separated by a predefined splitter
        $encoded_string = base64_encode($iv) . $this->encrypted_string_splitter . $encoded_string;

        // Return the combined string
        return $encoded_string;
    }


    /**
     * Decrypts a given encoded string using the AES-128-CBC cipher algorithm and returns the decoded string.
     *
     * @param string $encoded_string The encoded string that needs to be decrypted.
     * @return string The decrypted string.
     */
    private function openSSLDecrypt($encoded_string)
    {
        global $apbct;
        try {
            if (!is_string($encoded_string) || empty($encoded_string)) {
                throw new \Exception('Invalid or empty encoded string');
            }
            // Find the position of the splitter in the encoded string
            $splitter_position = strpos($encoded_string, $this->encrypted_string_splitter);

            if (empty($splitter_position)) {
                throw new \Exception('Can\'t split string');
            }

            // Extract the IV chunk from the encoded string
            $iv_chunk = substr($encoded_string, 0, $splitter_position);

            if (empty($iv_chunk)) {
                throw new \Exception('Can\'t get initializing vector string');
            }

            // Extract the encoded data chunk from the encoded string
            $encoded_data_chunk = substr($encoded_string, $splitter_position + strlen($this->encrypted_string_splitter));

            if (empty($encoded_data_chunk)) {
                throw new \Exception('Can\'t get encoded data');
            }

            if (!function_exists('base64_decode')) {
                throw new \Exception('Can\'t run base64_decode');
            }

            // Decode the IV chunk from base64
            $iv_chunk_decoded = base64_decode($iv_chunk);

            if (empty($iv_chunk_decoded)) {
                throw new \Exception('Can\'t decode initializing vector string');
            }

            // Decrypt the encoded data chunk using the specified cipher algorithm, secret key, and IV
            $decoded_string = @openssl_decrypt($encoded_data_chunk, $this->cipher_algo, $this->secret_key, 0, $iv_chunk_decoded);

            if (empty($decoded_string)) {
                throw new \Exception('Can\'t finish SSL decryption');
            }

            // Return the decrypted string
            return $decoded_string;
        } catch (\Exception $e) {
            //todo catch errors on higher level
            $get_last_error = error_get_last();
            $get_last_error = isset($get_last_error['message']) ? $get_last_error['message'] : 'no PHP error';
            $apbct->errorAdd('email_encoder', esc_html($e->getMessage()) . ', backtrace: ' . $get_last_error);
            return '';
        }
    }
}
