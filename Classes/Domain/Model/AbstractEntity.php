<?php

namespace FelixNagel\T3extblog\Domain\Model;

/**
 * This file is part of the "t3extblog" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use FelixNagel\T3extblog\Domain\Repository\CommentRepository;
use FelixNagel\T3extblog\Domain\Repository\PostRepository;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity as CoreAbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

/**
 * AbstractEntity.
 */
abstract class AbstractEntity extends CoreAbstractEntity
{
    /**
     * Creation date and time.
     *
     * @var \DateTime
     */
    protected $crdate;

    /**
     * commentRepository.
     *
     * @var \FelixNagel\T3extblog\Domain\Repository\CommentRepository
     */
    protected $commentRepository = null;

    /**
     * postRepository.
     *
     * @var \FelixNagel\T3extblog\Domain\Repository\PostRepository
     */
    protected $postRepository = null;

    /**
     * @var ObjectManagerInterface
     *
     */
    protected $objectManager = null;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function injectObjectManager(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @return \DateTime
     */
    public function getCrdate()
    {
        return $this->crdate;
    }

    /**
     * @return \DateTime
     */
    public function getCreateDate()
    {
        return $this->getCrdate();
    }

    /**
     * @param $crdate
     */
    public function setCrdate($crdate)
    {
        $this->crdate = $crdate;
    }

    /**
     * Get commentRepository.
     *
     * @return CommentRepository
     */
    protected function getCommentRepository()
    {
        if ($this->commentRepository === null) {
            $this->commentRepository = $this->objectManager->get(CommentRepository::class);
        }

        return $this->commentRepository;
    }

    /**
     * Get postRepository.
     *
     * @return PostRepository
     */
    protected function getPostRepository()
    {
        if ($this->postRepository === null) {
            $this->postRepository = $this->objectManager->get(PostRepository::class);
        }

        return $this->postRepository;
    }

    /**
     * Makes an array out of all public getter methods.
     *
     * @param bool $camelCaseKeys If set to false the array keys are TYPO3 cObj compatible
     *
     * @return array
     */
    public function toArray($camelCaseKeys = false)
    {
        $camelCaseProperties = ObjectAccess::getGettableProperties($this);

        if ($camelCaseKeys === true) {
            return $camelCaseProperties;
        }

        $data = [];
        foreach ($camelCaseProperties as $camelCaseFieldKey => $value) {
            $fieldKey = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $camelCaseFieldKey));

            // TYPO3 cObj edge case
            if ($camelCaseFieldKey === 'cType') {
                $fieldKey = ucfirst($camelCaseFieldKey);
            }

            $data[$fieldKey] = $value;
        }

        return $data;
    }

    /**
     * @param mixed $relation
     */
    protected function loadLazyRelation($relation)
    {
        if ($relation instanceof \TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy) {
            $relation->_loadRealInstance();
        }
    }

    /**
     * Serialization (sleep) helper.
     *
     * @return array Names of the properties to be serialized
     */
    public function __sleep()
    {
        return array_keys($this->getPropertiesForSerialization());
    }

    /**
     * @return array Names of the properties to be serialized
     */
    protected function getPropertiesForSerialization()
    {
        $properties = get_object_vars($this);

        // Remove properties not required if fully populated
        unset($properties['objectManager']);
        unset($properties['postRepository']);
        unset($properties['commentRepository']);

        // Remove lazy object storage as this will break post preview when serializing the post in form VH
        // @todo Fix this!
        foreach ($properties as $key => $property) {
            if ($property instanceof \TYPO3\CMS\Extbase\Persistence\Generic\LazyObjectStorage) {
                unset($properties[$key]);
            }
        }

        return $properties;
    }
}
