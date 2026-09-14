<?php

namespace App\Service;

use App\Repository\MediaRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class MediaService
{
    public function __construct(private RequestStack $requestStack, private MediaRepository $mediaRepository, private PaginatorInterface $paginator){}

    public function getPagination()
    {
        $request = $this->requestStack->getMainRequest();
        $page = $request->query->getInt('page', 1);
        $limit = 12;

        $mediaQuery = $this->mediaRepository->findBy([], ['id' => 'DESC']);

        return $this->paginator->paginate($mediaQuery, $page, $limit);
    }
}
