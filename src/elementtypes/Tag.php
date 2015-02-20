<?php
/**
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

namespace craft\app\elementtypes;

use Craft;
use craft\app\db\Command;
use craft\app\enums\AttributeType;
use craft\app\helpers\DbHelper;
use craft\app\models\BaseElementModel;
use craft\app\models\ElementCriteria as ElementCriteriaModel;
use craft\app\models\Tag as TagModel;

/**
 * The Tag class is responsible for implementing and defining tags as a native element type in Craft.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Tag extends BaseElementType
{
	// Public Methods
	// =========================================================================

	/**
	 * @inheritDoc ComponentTypeInterface::getName()
	 *
	 * @return string
	 */
	public function getName()
	{
		return Craft::t('app', 'Tags');
	}

	/**
	 * @inheritDoc ElementTypeInterface::hasContent()
	 *
	 * @return bool
	 */
	public function hasContent()
	{
		return true;
	}

	/**
	 * @inheritDoc ElementTypeInterface::hasTitles()
	 *
	 * @return bool
	 */
	public function hasTitles()
	{
		return true;
	}

	/**
	 * @inheritDoc ElementTypeInterface::isLocalized()
	 *
	 * @return bool
	 */
	public function isLocalized()
	{
		return true;
	}

	/**
	 * @inheritDoc ElementTypeInterface::getSources()
	 *
	 * @param string|null $context
	 *
	 * @return array|false
	 */
	public function getSources($context = null)
	{
		$sources = [];

		foreach (Craft::$app->tags->getAllTagGroups() as $tagGroup)
		{
			$key = 'taggroup:'.$tagGroup->id;

			$sources[$key] = [
				'label'    => Craft::t('app', $tagGroup->name),
				'criteria' => ['groupId' => $tagGroup->id]
			];
		}

		return $sources;
	}

	/**
	 * @inheritDoc ElementTypeInterface::defineTableAttributes()
	 *
	 * @param string|null $source
	 *
	 * @return array
	 */
	public function defineTableAttributes($source = null)
	{
		return [
			'title' => Craft::t('app', 'Title'),
		];
	}

	/**
	 * @inheritDoc ElementTypeInterface::defineCriteriaAttributes()
	 *
	 * @return array
	 */
	public function defineCriteriaAttributes()
	{
		return [
			'group'   => AttributeType::Mixed,
			'groupId' => AttributeType::Mixed,
			'order'   => [AttributeType::String, 'default' => 'content.title asc'],

			// TODO: Deprecated
			'name'    => AttributeType::String,
		];
	}

	/**
	 * @inheritDoc ElementTypeInterface::modifyElementsQuery()
	 *
	 * @param Command            $query
	 * @param ElementCriteriaModel $criteria
	 *
	 * @return mixed
	 */
	public function modifyElementsQuery(Command $query, ElementCriteriaModel $criteria)
	{
		$query
			->addSelect('tags.groupId')
			->innerJoin('{{%tags}} tags', 'tags.id = elements.id');

		if ($criteria->groupId)
		{
			$query->andWhere(DbHelper::parseParam('tags.groupId', $criteria->groupId, $query->params));
		}

		if ($criteria->group)
		{
			$query->innerJoin('{{%taggroups}} taggroups', 'taggroups.id = tags.groupId');
			$query->andWhere(DbHelper::parseParam('taggroups.handle', $criteria->group, $query->params));
		}

		// Backwards compatibility with deprecated params
		// TODO: Remove this code in Craft 4

		if ($criteria->name)
		{
			$query->andWhere(DbHelper::parseParam('content.title', $criteria->name, $query->params));
			Craft::$app->deprecator->log('tag_name_param', 'Tags’ ‘name’ param has been deprecated. Use ‘title’ instead.');
		}

		if (is_string($criteria->order))
		{
			$criteria->order = preg_replace('/\bname\b/', 'title', $criteria->order, -1, $count);

			if ($count)
			{
				Craft::$app->deprecator->log('tag_orderby_name', 'Ordering tags by ‘name’ has been deprecated. Order by ‘title’ instead.');
			}
		}
	}

	/**
	 * @inheritDoc ElementTypeInterface::populateElementModel()
	 *
	 * @param array $row
	 *
	 * @return array
	 */
	public function populateElementModel($row)
	{
		return TagModel::populateModel($row);
	}

	/**
	 * @inheritDoc ElementTypeInterface::getEditorHtml()
	 *
	 * @param BaseElementModel $element
	 *
	 * @return string
	 */
	public function getEditorHtml(BaseElementModel $element)
	{
		$html = Craft::$app->templates->renderMacro('_includes/forms', 'textField', [
			[
				'label'     => Craft::t('app', 'Title'),
				'locale'    => $element->locale,
				'id'        => 'title',
				'name'      => 'title',
				'value'     => $element->getContent()->title,
				'errors'    => $element->getErrors('title'),
				'first'     => true,
				'autofocus' => true,
				'required'  => true
			]
		]);

		$html .= parent::getEditorHtml($element);

		return $html;
	}

	/**
	 * @inheritDoc ElementTypeInterface::saveElement()
	 *
	 * @param BaseElementModel $element
	 * @param array            $params
	 *
	 * @return bool
	 */
	public function saveElement(BaseElementModel $element, $params)
	{
		return Craft::$app->tags->saveTag($element);
	}
}