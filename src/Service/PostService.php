<?php

namespace App\Service;

use App\Repository\PostRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class PostService
{
    public function __construct(private RequestStack $requestStack, private PostRepository $postRepository, private PaginatorInterface $paginator){}

    public function getPostPagination()
    {
        $request = $this->requestStack->getMainRequest();
        $page = $request->query->getInt('page', 1);
        $limit = 6;

        $mediaQuery = $this->postRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->paginator->paginate($mediaQuery, $page, $limit);
    }
}
