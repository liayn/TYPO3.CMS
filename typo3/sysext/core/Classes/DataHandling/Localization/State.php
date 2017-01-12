<?php
namespace TYPO3\CMS\Core\DataHandling\Localization;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Value object for l10n_state field value.
 */
class State
{
    const STATE_CUSTOM = 'custom';
    const STATE_PARENT = 'parent';
    const STATE_SOURCE = 'source';

    /**
     * @param string $tableName
     * @return null|State
     */
    public static function create(string $tableName)
    {
        if (!static::isApplicable($tableName)) {
            return null;
        }

        return GeneralUtility::makeInstance(
            static::class,
            $tableName
        );
    }

    /**
     * @param string $tableName
     * @param string|null $json
     * @return null|State
     */
    public static function fromJSON(string $tableName, string $json = null)
    {
        if (!static::isApplicable($tableName)) {
            return null;
        }

        $states = json_decode($json ?? '', true);
        return GeneralUtility::makeInstance(
            static::class,
            $tableName,
            $states ?? []
        );
    }

    /**
     * @param string $tableName
     * @return bool
     */
    public static function isApplicable(string $tableName)
    {
        return (
            static::hasColumns($tableName)
            && static::hasLanguageFieldName($tableName)
            && static::hasTranslationParentFieldName($tableName)
            && count(static::getFieldNames($tableName)) > 0
        );
    }

    /**
     * @param string $tableName
     * @return bool
     */
    protected static function hasColumns(string $tableName)
    {
        return (
            !empty($GLOBALS['TCA'][$tableName]['columns'])
            && is_array($GLOBALS['TCA'][$tableName]['columns'])
        );
    }

    /**
     * @param string $tableName
     * @return bool
     */
    protected static function hasLanguageFieldName(string $tableName)
    {
        return !empty($GLOBALS['TCA'][$tableName]['ctrl']['languageField']);
    }

    /**
     * @param string $tableName
     * @return bool
     */
    protected static function hasTranslationParentFieldName(string $tableName)
    {
        return !empty($GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField']);
    }

    /**
     * @param string $tableName
     * @return array
     */
    protected static function getFieldNames(string $tableName)
    {
        return array_keys(
            array_filter(
                $GLOBALS['TCA'][$tableName]['columns'],
                function(array $fieldConfiguration) {
                    return !empty(
                        $fieldConfiguration['config']
                            ['behaviour']['allowLanguageSynchronization']
                    );
                }
            )
        );
    }

    /**
     * @var string
     */
    protected $tableName;

    /**
     * @var array
     */
    protected $states;

    /**
     * @var array
     */
    protected $originalStates;

    /**
     * @param string $tableName
     * @param array $states
     */
    public function __construct(string $tableName, array $states = array())
    {
        $this->tableName = $tableName;
        $this->states = $states;
        $this->originalStates = $states;

        $this->states = $this->sanitize($states);
        $this->states = $this->enrich($states);
    }

    /**
     * @param array $states
     */
    public function update(array $states)
    {
        $this->states = array_merge(
            $this->states,
            $this->sanitize($states)
        );
    }

    /**
     * @return string|null
     */
    public function export()
    {
        if (empty($this->states)) {
            return null;
        }
        return json_encode($this->states);
    }

    /**
     * @return string[]
     */
    public function getModifiedFieldNames()
    {
        return array_keys(
            array_diff_assoc(
                $this->states,
                $this->originalStates
            )
        );
    }

    /**
     * @return bool
     */
    public function isModified()
    {
        return !empty($this->getModifiedFieldNames());
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    public function isUndefined(string $fieldName)
    {
        return !isset($this->states[$fieldName]);
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    public function isCustomState(string $fieldName)
    {
        return ($this->states[$fieldName] ?? null) === static::STATE_CUSTOM;
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    public function isParentState(string $fieldName)
    {
        return ($this->states[$fieldName] ?? null) === static::STATE_PARENT;
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    public function isSourceState(string $fieldName)
    {
        return ($this->states[$fieldName] ?? null) === static::STATE_SOURCE;
    }

    /**
     * @param string $fieldName
     * @return null|string
     */
    public function getState(string $fieldName)
    {
        return ($this->states[$fieldName] ?? null);
    }

    /**
     * Filters field names having a desired state.
     *
     * @param string $desiredState
     * @param bool $modified
     * @return string[]
     */
    public function filterFieldNames(string $desiredState, bool $modified = false)
    {
        if (!$modified) {
            $fieldNames = array_keys($this->states);
        } else {
            $fieldNames = $this->getModifiedFieldNames();
        }
        return array_filter(
            $fieldNames,
            function($fieldName) use ($desiredState) {
                return $this->states[$fieldName] === $desiredState;
            }
        );
    }

    /**
     * Filter out field names that don't exist in TCA.
     *
     * @param array $states
     * @return array
     */
    protected function sanitize(array $states)
    {
        $fieldNames = static::getFieldNames($this->tableName);
        return array_intersect_key(
            $states,
            array_combine($fieldNames, $fieldNames)
        );
    }

    /**
     * Add missing states for field names.
     *
     * @param array $states
     * @return array
     */
    protected function enrich(array $states)
    {
        foreach (static::getFieldNames($this->tableName) as $fieldName) {
            if (!empty($states[$fieldName])) {
                continue;
            }
            $states[$fieldName] = static::STATE_PARENT;
        }
        return $states;
    }
}