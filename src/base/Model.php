<?php
/**
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

namespace craft\app\base;

use Craft;
use craft\app\dates\DateTime;
use craft\app\enums\AttributeType;
use craft\app\helpers\DateTimeHelper;
use craft\app\helpers\JsonHelper;
use craft\app\helpers\ModelHelper;
use craft\app\helpers\StringHelper;
use yii\base\UnknownMethodException;

/**
 * Model base class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
abstract class Model extends \yii\base\Model
{
	// Properties
	// =========================================================================

	/**
	 * @var string
	 */
	protected $classSuffix = 'Model';

	/**
	 * @var bool Whether this model should be strict about only allowing values to be set on defined attributes
	 */
	protected $strictAttributes = true;

	/**
	 * @var
	 */
	private $_classHandle;

	/**
	 * @var
	 */
	private $_attributeConfigs;

	/**
	 * @var
	 */
	private $_attributes;

	/**
	 * @var
	 */
	private $_extraAttributeNames;

	// Public Methods
	// =========================================================================

	/**
	 * Constructor
	 *
	 * @param mixed $attributes
	 *
	 * @return Model
	 */
	public function __construct($attributes = null)
	{
		if (!$this->strictAttributes)
		{
			$this->_extraAttributeNames = [];
		}

		ModelHelper::populateAttributeDefaults($this);
		$this->setAttributes($attributes);

		$this->attachBehaviors($this->behaviors());
	}

	/**
	 * PHP getter magic method.
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get($name)
	{
		if (in_array($name, $this->attributeNames()))
		{
			return $this->getAttribute($name);
		}
		else
		{
			return parent::__get($name);
		}
	}

	/**
	 * PHP setter magic method.
	 *
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	public function __set($name, $value)
	{
		if ($this->setAttribute($name, $value) === false)
		{
			parent::__set($name, $value);
		}
	}

	/**
	 * Magic __call() method, used for chain-setting attribute values.
	 *
	 * @param string $name
	 * @param array  $arguments
	 *
	 * @return Model
	 * @throws UnknownMethodException when calling an unknown method, if [[$strictAttributes]] isn’t enabled.
	 */
	public function __call($name, $arguments)
	{
		try
		{
			return parent::__call($name, $arguments);
		}
		catch (UnknownMethodException $e)
		{
			// Is this one of our attributes?
			if (!$this->strictAttributes || in_array($name, $this->attributeNames()))
			{
				$copy = $this->copy();

				if (count($arguments) == 1)
				{
					$copy->setAttribute($name, $arguments[0]);
				}
				else
				{
					$copy->setAttribute($name, $arguments);
				}

				return $copy;
			}

			throw $e;
		}
	}

	/**
	 * Treats attributes defined by defineAttributes() as properties.
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public function __isset($name)
	{
		if (parent::__isset($name) || in_array($name, $this->attributeNames()))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Populates a new model instance with a given set of attributes.
	 *
	 * @param mixed $values
	 *
	 * @return Model
	 */
	public static function populateModel($values)
	{
		$class = get_called_class();
		return new $class($values);
	}

	/**
	 * Mass-populates models based on an array of attribute arrays.
	 *
	 * @param array       $data
	 * @param string|null $indexBy
	 *
	 * @return array
	 */
	public static function populateModels($data, $indexBy = null)
	{
		$models = [];

		if (is_array($data))
		{
			foreach ($data as $values)
			{
				$model = static::populateModel($values);

				if ($indexBy)
				{
					$models[$model->$indexBy] = $model;
				}
				else
				{
					$models[] = $model;
				}
			}
		}

		return $models;
	}

	/**
	 * Treats attributes defined by defineAttributes() as array offsets.
	 *
	 * @param mixed $offset
	 *
	 * @return bool
	 */
	public function offsetExists($offset)
	{
		if (parent::offsetExists($offset) || in_array($offset, $this->attributeNames()))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Get the class name, sans namespace and suffix.
	 *
	 * @return string
	 */
	public function getClassHandle()
	{
		if (!isset($this->_classHandle))
		{
			// Chop off the namespace
			$classHandle = mb_substr(get_class($this), StringHelper::length(__NAMESPACE__) + 1);

			// Chop off the class suffix
			$suffixLength = StringHelper::length($this->classSuffix);

			if (mb_substr($classHandle, -$suffixLength) == $this->classSuffix)
			{
				$classHandle = mb_substr($classHandle, 0, -$suffixLength);
			}

			$this->_classHandle = $classHandle;
		}

		return $this->_classHandle;
	}

	/**
	 * Returns this model's normalized attribute configs.
	 *
	 * @return array
	 */
	public function getAttributeConfigs()
	{
		if (!isset($this->_attributeConfigs))
		{
			$this->_attributeConfigs = [];

			foreach ($this->defineAttributes() as $name => $config)
			{
				$this->_attributeConfigs[$name] = ModelHelper::normalizeAttributeConfig($config);
			}
		}

		return $this->_attributeConfigs;
	}

	/**
	 * Returns the list of this model's attribute names.
	 *
	 * @return array
	 */
	public function attributeNames()
	{
		$attributeNames = array_keys($this->getAttributeConfigs());

		if (!$this->strictAttributes)
		{
			$attributeNames = array_merge($attributeNames, $this->_extraAttributeNames);
		}

		return $attributeNames;
	}

	/**
	 * Returns a list of the names of the extra attributes that have been saved on this model, if it's not strict.
	 *
	 * @return array
	 */
	public function getExtraAttributeNames()
	{
		return $this->_extraAttributeNames;
	}

	/**
	 * Returns an array of attribute values.
	 *
	 * @param null $names
	 * @param bool $flattenValues Will change a DateTime object to a timestamp, Mixed to array, etc. Useful for saving
	 *                            to DB or sending over a web service.
	 *
	 * @return array
	 */
	public function getAttributes($names = null, $flattenValues = false)
	{
		$values = [];

		foreach ($this->attributeNames() as $name)
		{
			if ($names === null || in_array($name, $names))
			{
				$values[$name] = $this->getAttribute($name, $flattenValues);
			}
		}

		return $values;
	}

	/**
	 * Gets an attribute’s value.
	 *
	 * @param string $name         The attribute’s name.
	 * @param bool   $flattenValue
	 *
	 * @return mixed
	 */
	public function getAttribute($name, $flattenValue = false)
	{
		if (isset($this->_attributes[$name]))
		{
			if ($flattenValue)
			{
				return ModelHelper::packageAttributeValue($this->_attributes[$name]);
			}
			else
			{
				return $this->_attributes[$name];
			}
		}
	}

	/**
	 * Sets an attribute's value.
	 *
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return bool
	 */
	public function setAttribute($name, $value)
	{
		if (!$this->strictAttributes || in_array($name, $this->attributeNames()))
		{
			// Is this a normal attribute?
			if (array_key_exists($name, $this->_attributeConfigs))
			{
				$attributes = $this->getAttributeConfigs();
				$config = $attributes[$name];

				// Handle special case attribute types
				switch ($config['type'])
				{
					case AttributeType::DateTime:
					{
						if ($value)
						{
							if (!($value instanceof \DateTime))
							{
								if (DateTimeHelper::isValidTimeStamp($value))
								{
									$value = new DateTime('@'.$value);
								}
								else
								{
									$value = DateTime::createFromString($value);
								}
							}
						}
						else
						{
							// No empty strings allowed!
							$value = null;
						}

						break;
					}
					case AttributeType::Mixed:
					{
						if ($value && is_string($value) && StringHelper::contains('{[', $value[0]))
						{
							// Presumably this is JSON.
							$value = JsonHelper::decode($value);
						}

						if (is_array($value))
						{
							if ($config['model'])
							{
								$class = __NAMESPACE__.'\\'.$config['model'];
								$value = $class::populateModel($value);
							}
							else
							{
								$value = ModelHelper::expandModelsInArray($value);
							}
						}

						break;
					}
				}
			}
			// Is this the first time this extra attribute has been set?
			else if (!array_key_exists($name, $this->_extraAttributeNames))
			{
				$this->_extraAttributeNames[] = $name;
			}

			$this->_attributes[$name] = $value;
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Sets multiple attribute values at once.
	 *
	 * @param mixed $values
	 *
	 * @return null
	 */
	public function setAttributes($values, $safeOnly = true)
	{
		// If this is a model, get the actual attributes on it
		if ($values instanceof \yii\base\Model)
		{
			$model = $values;
			$values = $model->getAttributes();

			// Is this a record?
			if ($model instanceof \yii\db\ActiveRecord)
			{
				// See if any of this model's attributes map to eager-loaded relations on the record
				foreach ($this->attributeNames() as $name)
				{
					if ($model->isRelationPopulated($name))
					{
						$this->setAttribute($name, $model->$name);
					}
				}
			}
		}

		if (is_array($values) || $values instanceof \Traversable)
		{
			foreach ($values as $name => $value)
			{
				$this->setAttribute($name, $value);
			}
		}
	}

	/**
	 * Returns this model's validation rules.
	 *
	 * @return array
	 */
	public function rules()
	{
		return ModelHelper::getRules($this);
	}

	/**
	 * Returns the attribute labels.
	 *
	 * @return array
	 */
	public function attributeLabels()
	{
		return ModelHelper::getAttributeLabels($this);
	}

	/**
	 * Validates all of the attributes for the current model. Any attributes that fail validation will additionally get
	 * logged to the `craft/storage/logs` folder as a warning.
	 *
	 * @param null $attributes
	 * @param bool $clearErrors
	 *
	 * @return bool
	 */
	public function validate($attributes = null, $clearErrors = true)
	{
		if (parent::validate($attributes, $clearErrors))
		{
			return true;
		}

		foreach ($this->getErrors() as $attribute => $errorMessages)
		{
			foreach ($errorMessages as $errorMessage)
			{
				Craft::warning(get_class($this).'->'.$attribute.' failed validation: '.$errorMessage, __METHOD__);
			}
		}

		return false;
	}

	/**
	 * Returns all errors in a single list.
	 *
	 * @return array
	 */
	public function getAllErrors()
	{
		$errors = [];

		foreach ($this->getErrors() as $attributeErrors)
		{
			$errors = array_merge($errors, $attributeErrors);
		}

		return $errors;
	}

	/**
	 * Returns a copy of this model.
	 *
	 * @return Model
	 */
	public function copy()
	{
		$class = get_class($this);
		return new $class($this->getAttributes());
	}

	// Deprecated Methods
	// -------------------------------------------------------------------------

	/**
	 * Returns the first error of the specified attribute.
	 *
	 * @param string $attribute The attribute name.
	 * @return string The error message. Null is returned if no error.
	 *
	 * @deprecated in 3.0. Use [[getFirstError()]] instead.
	 */
	public function getError($attribute)
	{
		Craft::$app->deprecator->log('Model::getError()', 'getError() has been deprecated. Use getFirstError() instead.');
		return $this->getFirstError($attribute);
	}

	// Protected Methods
	// =========================================================================

	/**
	 * Defines this model's attributes.
	 *
	 * @return array
	 */
	protected function defineAttributes()
	{
		return [];
	}
}