<?php

namespace FelixNagel\T3extblog\Validation\Validator;

/**
 * This file is part of the "t3extblog" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use FelixNagel\T3extblog\Domain\Model\Comment;
use TYPO3\CMS\Extbase\Validation\Exception\InvalidValidationOptionsException;

/**
 * Validator for the comment email field that respects,
 * if the email requirement is enabled or not.
 */
class CommentEmailValidator extends AbstractValidator
{
    protected function isValid($value)
    {
        if (!$value instanceof Comment) {
            throw new InvalidValidationOptionsException('No valid comment given!', 1592253083);
        }

        if (empty($value->getEmail())) {
            if ($this->getConfiguration('blogsystem.comments.requireEmail')) {
                $this->addErrorForProperty('email', 'Email address is required.', 1592252730);
            } elseif ($this->getConfiguration('blogsystem.comments.subscribeForComments') && $value->getSubscribe()) {
                $this->addErrorForProperty('email', 'Email address is required for subscription.', 1592252731);
            }
        }
    }
}
