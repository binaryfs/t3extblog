<?php

namespace FelixNagel\T3extblog\Dashboard\Provider;

/**
 * This file is part of the "t3extblog" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use FelixNagel\T3extblog\Domain\Repository\CommentRepository;

abstract class AbstractCommentListDataProvider extends AbstractListDataProvider
{
    protected CommentRepository $commentRepository;

    protected array $options = [
        'limit' => 10,
    ];

    
    public function __construct(CommentRepository $commentRepository, array $options = [])
    {
        $this->commentRepository = $commentRepository;
        $this->options = array_merge(
            $this->options,
            $options
        );
    }
}
