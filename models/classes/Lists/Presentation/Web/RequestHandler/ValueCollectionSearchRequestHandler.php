<?php

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2020-2021 (original work) Open Assessment Technologies SA;
 *
 * @author Sergei Mikhailov <sergei.mikhailov@taotesting.com>
 */

declare(strict_types=1);

namespace oat\tao\model\Lists\Presentation\Web\RequestHandler;

use common_exception_BadRequest as BadRequestException;
use core_kernel_classes_Property as RdfProperty;
use oat\tao\model\Lists\Business\Domain\ValueCollectionSearchRequest;
use oat\tao\model\Lists\Business\Input\ValueCollectionSearchInput;
use oat\tao\model\Lists\Presentation\Web\RequestValidator\ValueCollectionSearchRequestValidator;
use oat\tao\model\service\InjectionAwareService;
use Psr\Http\Message\ServerRequestInterface;
use tao_helpers_Uri as Id;

class ValueCollectionSearchRequestHandler extends InjectionAwareService
{
    public const SERVICE_ID = 'tao/ValueCollectionSearchRequestHandler';

    public const QUERY_PARAMETER_ID = 'propertyUri';
    public const QUERY_PARAMETER_PARENT_LIST_VALUES = 'parentListValues';
    public const QUERY_PARAMETER_SUBJECT = 'subject';
    public const QUERY_PARAMETER_EXCLUDE = 'exclude';

    private const SEARCH_LIMIT = 20;

    /** @var ValueCollectionSearchRequestValidator */
    private $requestValidator;

    public function __construct(ValueCollectionSearchRequestValidator $requestValidator)
    {
        parent::__construct();

        $this->requestValidator = $requestValidator;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ValueCollectionSearchInput
     *
     * @throws BadRequestException
     */
    public function handle(ServerRequestInterface $request): ValueCollectionSearchInput
    {
        $this->requestValidator->validate($request);

        $queryParameters = $request->getQueryParams();

        $propertyUri = Id::decode(
            $queryParameters[self::QUERY_PARAMETER_ID]
        );

        $searchRequest = (new ValueCollectionSearchRequest())
            ->setLimit(self::SEARCH_LIMIT)
            ->setPropertyUri($propertyUri);

        if (!empty($queryParameters[self::QUERY_PARAMETER_PARENT_LIST_VALUES])) {
            $parentListValues = $queryParameters[self::QUERY_PARAMETER_PARENT_LIST_VALUES];

            array_walk(
                $parentListValues,
                function (&$value) {
                    $value = Id::decode($value);
                }
            );

            $searchRequest->setParentListValues(...$parentListValues);
        }

        $listUri = $this->getPropertyListUri($propertyUri);

        if ($listUri !== null) {
            $searchRequest->setValueCollectionUri($listUri);
        }

        $subject = trim($queryParameters[self::QUERY_PARAMETER_SUBJECT] ?? '');

        if (!empty($subject)) {
            $searchRequest->setSubject($subject);
        }

        foreach ($queryParameters[self::QUERY_PARAMETER_EXCLUDE] ?? [] as $excluded) {
            $searchRequest->addExcluded(
                Id::decode($excluded)
            );
        }

        return new ValueCollectionSearchInput($searchRequest);
    }

    /**
     * @param string $propertyUri
     *
     * @return string|null
     */
    protected function getPropertyListUri(string $propertyUri): ?string
    {
        $property = new RdfProperty($propertyUri);
        $list = $property->getRange();

        if ($list !== null) {
            return $list->getUri();
        }

        return null;
    }
}
