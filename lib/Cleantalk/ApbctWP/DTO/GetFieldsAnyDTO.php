<?php

namespace Cleantalk\ApbctWP\DTO;

use Cleantalk\Templates\DTO;

/**
 * Class GetFieldsAnyDTO
 *
 * Used to correctly collect GetFieldsAny process data.
 * Obligatory properties:
 * <ul>
 * <li>email(string)</li>
 * <li>emails_array(string[])</li>
 * <li>nickname(string)</li>
 * <li>subject(string)</li>
 * <li>contact(bool)</li>
 * <li>message(string[])</li>
 * </ul>
 *
 * To get assoc array of all properties use getArray() method.
 * @since 6.48
 * @version 1.0.0
 * @package Cleantalk\ApbctWP\DTO
 * @psalm-suppress InvalidClass
 */
class GetFieldsAnyDTO extends DTO
{
    /**
     * Sender email.
     * @var string
     */
    public $email = '';
    /**
     * Array of emails.
     * @var array
     */
    public $emails_array = array();
    /**
     * Nickname.
     * Will be concatenated from nickname_first, nickname_last and nickname_nick if not provided during processing.
     * @var string
     */
    public $nickname = '';
    /**
     * Nickname first part.
     * @var string
     */
    public $nickname_first = '';
    /**
     * Nickname last part.
     * @var string
     */
    public $nickname_last = '';
    /**
     * Nickname nick part.
     * @var string
     */
    public $nickname_nick = '';
    /**
     * Subject.
     * @var string
     */
    public $subject = '';
    /**
     * Is contact form?
     * @var bool
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $contact = true;
    /**
     * Message array.
     * @var array
     */
    public $message = array();

    /**
     * Is registration form?
     * @var bool
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $register = false;

    protected $obligatory_properties = array(
        'email',
        'emails_array',
        'nickname',
        'subject',
        'contact',
        'message',
    );

    public function __construct($params)
    {
        parent::__construct($params);
    }
}
