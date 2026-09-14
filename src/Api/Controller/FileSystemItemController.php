<?php

namespace Wexample\SymfonyFile\Api\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\LengthQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\PageQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\StringQueryOption;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyFile\Api\Normalizer\Entity\FileSystemItem\DefaultFileSystemItemNormalizer;
use Wexample\SymfonyFile\Service\FileSystemItemIndexer;
use Wexample\SymfonyFile\Service\FileSystemItemScannerFactory;
use Wexample\SymfonyHelpers\Controller\AbstractController;

#[Route(path: 'api/file-system-item/', name: 'api_file_system_item_')]
class FileSystemItemController extends AbstractApiController
{
    final public const ROUTE_LIST = 'list';

    final public const QUERY_OPTION_PARENT = 'parent';

    final public const LIST_PAGE_LENGTH = 1000;

    #[Route(path: '{root}/list', name: self::ROUTE_LIST, methods: AbstractController::ROUTE_OPTIONS_METHOD_ONLY_GET, options: AbstractController::ROUTE_OPTIONS_ONLY_EXPOSE)]
    #[PageQueryOption]
    // A level comes whole in practice, and the cap is there for the ones nobody
    // wrote by hand: a node_modules must not be scanned, serialised and sent in
    // one piece just because someone opened it.
    #[LengthQueryOption(default: self::LIST_PAGE_LENGTH)]
    #[StringQueryOption(key: self::QUERY_OPTION_PARENT, default: '')]
    public function list(
        string $root,
        Request $request,
        FileSystemItemScannerFactory $scannerFactory,
        FileSystemItemIndexer $indexer,
        DefaultFileSystemItemNormalizer $normalizer,
    ): ApiResponse {
        $scanner = $scannerFactory->getScanner($root);

        if (null === $scanner) {
            throw $this->createNotFoundException('Unknown file system root: '.$root);
        }

        $parent = self::getQueryOptionValue(
            $request,
            self::QUERY_OPTION_PARENT,
            ''
        );

        // Read from the disk the first time this level is asked for, and from
        // the index every time after.
        $items = $indexer->level($scanner, $parent);

        // Counted, because a level silently cut in half is worse than the read it
        // costs: the client needs the total to offer the rest.
        $pagination = self::getQueryOptionPagination($request, count($items));

        return self::apiResponsePaginated(
            pagination: $pagination,
            items: $normalizer->normalizeCollection(
                array_slice($items, $pagination->getOffset(), $pagination->length)
            )
        );
    }
}
