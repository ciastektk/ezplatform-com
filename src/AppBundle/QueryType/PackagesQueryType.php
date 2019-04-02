<?php

/**
 * QueryType for Package ContentType.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace AppBundle\QueryType;

use eZ\Publish\Core\QueryType\QueryType;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use Netgen\TagsBundle\API\Repository\Values\Content\Query as TagQuery;

/**
 * Class PackagesQueryType.
 */
class PackagesQueryType implements QueryType
{
    /**
     * Builds and returns the Query object.
     *
     * @param array $parameters A hash of parameters that will be used to build the Query
     *
     * @return \eZ\Publish\API\Repository\Values\Content\LocationQuery
     */
    public function getQuery(array $parameters = []): LocationQuery
    {
        $options = [];

        $criteria = [
            new Query\Criterion\ParentLocationId($parameters['parent_location_id']),
            new Query\Criterion\Visibility(Query\Criterion\Visibility::VISIBLE),
            new Query\Criterion\ContentTypeIdentifier('package'),
        ];

        if (isset($parameters['tag_id'])) {
            $criteria[] = new TagQuery\Criterion\TagId($parameters['tag_id']);
        }

        if (isset($parameters['search']) && !empty($parameters['search'])) {
            $options['query'] = new Query\Criterion\FullText($parameters['search'], [
                'customFields' => [
                    'package_id',
                    'name',
                    'description',
                    'packagist_url',
                ],
            ]);
        }

        $options['filter'] = new Query\Criterion\LogicalAnd($criteria);

        if (isset($parameters['order'])) {
            if ($parameters['order'] === 'latestUpdate') {
                $options['sortClauses'] = [new Query\SortClause\Field('package', 'updated', Query::SORT_DESC)];
            } elseif ($parameters['order'] === 'stars') {
                $options['sortClauses'] = [new Query\SortClause\Field('package', 'stars', Query::SORT_DESC)];
            } elseif ($parameters['order'] === 'downloads') {
                $options['sortClauses'] = [new Query\SortClause\Field('package', 'downloads', Query::SORT_DESC)];
            } else {
                $options['sortClauses'] = [new Query\SortClause\DateModified(Query::SORT_DESC)];
            }
        }

        if (isset($parameters['limit'])) {
            $options['limit'] = $parameters['limit'];
        }

        if (isset($parameters['offset'])) {
            $options['offset'] = $parameters['offset'];
        }

        return new LocationQuery($options);
    }

    /**
     * Returns an array listing the parameters supported by the QueryType.
     *
     * @return array
     */
    public function getSupportedParameters()
    {
        return [
            'parent_location_id',
            'limit',
            'offset',
            'order',
        ];
    }

    /**
     * Returns the QueryType name.
     *
     * @return string
     */
    public static function getName()
    {
        return 'AppBundle:Packages';
    }
}
