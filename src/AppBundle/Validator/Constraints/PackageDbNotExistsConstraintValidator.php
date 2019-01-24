<?php

/**
 * PackageDbNotExistsConstraintValidator - Custom Form Constraint Validator Class for validation if given package exists in catalog.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace AppBundle\Validator\Constraints;

use AppBundle\Url\UrlBuilder;
use AppBundle\ValueObject\RepositoryMetadata;
use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\ContentTypeIdentifier;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Field;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\LogicalAnd;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Operator;
use EzSystems\EzPlatformAdminUi\UI\Dataset\ContentDraftsDataset;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\MissingOptionsException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Class PackageDbExistsConstraintValidator
 *
 * @package AppBundle\Validator\Constraints
 */
class PackageDbNotExistsConstraintValidator extends ConstraintValidator
{
    private static $VALIDATION_MESSAGE = [
        'packagist_url' => 'package',
        'name' => 'package name'
    ];

    /**
     * @var \eZ\Publish\API\Repository\SearchService
     */
    private $searchService;

    /**
     * @var \EzSystems\EzPlatformAdminUi\UI\Dataset\ContentDraftsDataset
     */
    private $contentDraftsDataset;

    /**
     * @var \AppBundle\Url\UrlBuilder
     */
    private $urlBuilder;

    public function __construct(
        SearchService $searchService,
        ContentDraftsDataset $contentDraftsDataset,
        UrlBuilder $urlBuilder
    ) {
        $this->searchService = $searchService;
        $this->contentDraftsDataset = $contentDraftsDataset;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @param mixed $value
     * @param Constraint $constraint
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof PackageDbNotExistsConstraint) {
            throw new UnexpectedTypeException($constraint, PackageDbNotExistsConstraint::class);
        }

        if (!$value) {
            return;
        }

        $params = [
            'parent_location_id' => $constraint->getPackageListLocationId(),
            'target_field' => $constraint->getTargetField(),
            'search' => $value
        ];

        $drafts = $this->contentDraftsDataset->load();
        $repositoryMetadata = new RepositoryMetadata($value);

        $repositoryId = $this->urlBuilder->urlGlue($repositoryMetadata->getUsername(), $repositoryMetadata->getRepositoryName());

        unset($repositoryMetadata);

        $isDraftExist = in_array($repositoryId, array_column($drafts->getContentDrafts(), 'name'));

        if ($isDraftExist) {
            $this->context
                ->buildViolation($constraint->messageWaitingForApproval)
                ->addViolation();
        }

        $query = $this->getPackageQuery($params);

        if (!$isDraftExist && $this->searchService->findContentInfo($query)->totalCount > 0) {
            $this->context
                ->buildViolation($constraint->message)
                ->setParameter('{{ name }}', $this->getMessageValue($params['target_field']))
                ->addViolation();
        }
    }

    /**
     * @param array $params
     *
     * @return Query
     */
    private function getPackageQuery(array $params): Query
    {
        $query = new Query();

        if (!isset($params['parent_location_id']) ||
            !isset($params['target_field']) ||
            !isset($params['search'])
            ) {
            throw new MissingOptionsException('One of required options are missing: \'parent_location_id\', \'target_field\', \'search\'  ', []);
        }

        $query->query = new LogicalAnd([
            new Query\Criterion\ParentLocationId($params['parent_location_id']),
            new ContentTypeIdentifier('package'),
            new Field($params['target_field'], Operator::EQ, $params['search'])
        ]);

        return $query;
    }

    /**
     * @param string $targetField
     *
     * @return mixed|string
     */
    private function getMessageValue(string $targetField): string
    {
        return array_key_exists($targetField, self::$VALIDATION_MESSAGE) ? self::$VALIDATION_MESSAGE[$targetField] : '';
    }
}
