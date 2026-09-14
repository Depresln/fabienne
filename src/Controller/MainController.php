<?php

namespace App\Controller;

use App\Entity\Media;
use App\Entity\Post;
use App\Entity\Contenu;
use App\Entity\Comment;
use App\Form\CommentType;
use App\Form\ContenuType;
use App\Form\PostType;
use App\Repository\ContenuRepository;
use App\Repository\CommentRepository;
use App\Service\ImageOptimizer;
use App\Service\MediaService;
use App\Service\PostService;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Monolog\DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class MainController extends AbstractController
{
    private $imageOptimizer;

    public function __construct(
        private EntityManagerInterface $em,
        ImageOptimizer $imageOptimizer,
        private CommentRepository $commentRepository,
    )
    {
        $this->imageOptimizer = $imageOptimizer;
    }

    protected function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        if ($this->getUser()) {
            $parameters['pendingComments'] = $this->commentRepository->countPending();
        }

        return parent::render($view, $parameters, $response);
    }

    #[Route('/', name: 'home')]
    public function index(PostService $postService, ContenuRepository $contenuRepository): Response
    {
        return $this->render('main/index.html.twig', [
            'posts' => $postService->getPostPagination(),
            'contenu' => $contenuRepository->findOneBy([]),
        ]);
    }

    #[Route('/modifier-accueil', name: 'edit_home_content', methods: ['GET', 'POST'])]
    public function editHomeContent(Request $request, ContenuRepository $contenuRepository): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $contenu = $contenuRepository->findOneBy([]) ?? new Contenu();
        $form = $this->createForm(ContenuType::class, $contenu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($contenu);
            $this->em->flush();

            $this->addFlash('success', 'Le message d’accueil a été mis à jour.');

            return $this->redirectToRoute('home');
        }

        return $this->render('main/edit_home_content.html.twig', [
            'content_form' => $form->createView(),
        ]);
    }

    #[Route('/galerie', name: 'gallery')]
    public function gallery(MediaService $mediaService): Response
    {
        return $this->render('main/gallery.html.twig', [
            'media' => $mediaService->getPagination()
        ]);
    }

    #[Route('/blog', name: 'blog')]
    public function blog(Request $request): Response
    {
        $post = $this->em->getRepository(Post::class)->find($request->query->getInt('id'));

        if (!$post) {
            throw $this->createNotFoundException('Article introuvable.');
        }

        if ($request->isMethod('GET')) {
            $post->incrementViewCount();
            $this->em->flush();
        }

        $comment = new Comment();
        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setPost($post);
            $this->em->persist($comment);
            $this->em->flush();

            $this->addFlash('success', 'Merci ! Votre commentaire sera publié après validation.');

            return $this->redirectToRoute('blog', ['id' => $post->getId()]);
        }

        return $this->render('main/blog.html.twig', [
            'post' => $post,
            'comment_form' => $form->createView(),
        ]);
    }

    #[Route('/image/{id}/vue', name: 'media_view', methods: ['POST'])]
    public function recordMediaView(Media $media): JsonResponse
    {
        $media->incrementViewCount();
        $this->em->flush();

        return $this->json(['viewCount' => $media->getViewCount()]);
    }

    #[Route('/commentaire/{id}/valider', name: 'approve_comment', methods: ['POST'])]
    public function approveComment(Request $request, Comment $comment): Response
    {
        if (!$this->getUser() || !$this->isCsrfTokenValid('approve-comment-'.$comment->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('home');
        }

        $comment->setApproved(true);
        $this->em->flush();
        $this->addFlash('success', 'Commentaire publié.');

        if ($request->request->get('return_to') === 'comments') {
            return $this->redirectToRoute('admin_comments');
        }

        return $this->redirectToRoute('blog', ['id' => $comment->getPost()?->getId()]);
    }

    #[Route('/commentaire/{id}/supprimer', name: 'delete_comment', methods: ['POST'])]
    public function deleteComment(Request $request, Comment $comment): Response
    {
        $postId = $comment->getPost()?->getId();

        if (!$this->getUser() || !$this->isCsrfTokenValid('delete-comment-'.$comment->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('home');
        }

        $this->em->remove($comment);
        $this->em->flush();
        $this->addFlash('success', 'Commentaire supprimé.');

        if ($request->request->get('return_to') === 'comments') {
            return $this->redirectToRoute('admin_comments');
        }

        return $this->redirectToRoute('blog', ['id' => $postId]);
    }

    #[Route('/admin/commentaires', name: 'admin_comments', methods: ['GET'])]
    public function adminComments(Request $request, CommentRepository $commentRepository, PaginatorInterface $paginator): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('home');
        }

        return $this->render('main/comments.html.twig', [
            'comments' => $paginator->paginate(
                $commentRepository->createQueryBuilder('comment')
                    ->orderBy('comment.approved', 'ASC')
                    ->addOrderBy('comment.createdAt', 'DESC'),
                $request->query->getInt('page', 1),
                10,
            ),
        ]);
    }

    #[Route('/publier', name: 'publish')]
    public function post(Request $request, SluggerInterface $slugger)
    {
        if($this->getUser()){
            $form = $this->createForm(PostType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $postData = $form->getData();
                $postedMedia = $form->get('media')->getData();

                // if post has media but no title and description, don't create a post but add files to the gallery
                if($postedMedia && !$postData->getTitle() && !$postData->getDescription()){
                    foreach($postedMedia as $postedMedium){
                        $media = $this->mediaHandler($postedMedium, $slugger);

                        $this->em->persist($media);
                        unset($postedMedium);
                    }
                    $this->em->flush();

                    $this->addFlash('success', 'Image(s) ajoutée(s) à la galerie avec succès !');
                    return $this->redirectToRoute('home');
                } else {
                    // normal post
                    $post = new Post();
                    // if title but no content
                    if(!$postedMedia && !$postData->getDescription()){
                        $this->addFlash('danger', 'L\'article doit avoir un contenu.');
                        return $this->redirectToRoute('publish');
                    } elseif(!$postedMedia && !$postData->getTitle() || !$postData->getTitle() && $postData->getDescription()){
                        // if no title nor media
                        $this->addFlash('danger', 'L\'article doit avoir un titre.');
                        return $this->redirectToRoute('publish');
                    } else {
                        $post->setTitle($postData->getTitle());
                        $postData->getDescription() ? $post->setDescription($postData->getDescription()) : $post->setDescription(null);

                        if($postedMedia){
                            foreach($postedMedia as $postedMedium){
                                $media = $this->mediaHandler($postedMedium, $slugger);

                                $media->setPost($post);
                                $this->em->persist($media);
                                unset($postedMedium);
                            }
                        }
                        $datetime = new \DateTimeImmutable();
                        $post->setCreatedAt($datetime->setTimezone(new DateTimeZone("Europe/Paris")));
                        $post->setModifiedAt($datetime->setTimezone(new DateTimeZone("Europe/Paris")));

                        $this->em->persist($post);
                        $this->em->flush();

                        $this->addFlash('success', 'Article créé avec succès !');
                        return $this->redirectToRoute('home');
                    }
                }
            }

            return $this->render('main/publish.html.twig', [
                'post_form' => $form->createView()
            ]);
        } else {
            return $this->redirectToRoute('home');
        }
    }

    #[Route('/editer', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SluggerInterface $slugger)
    {
        if($this->getUser()){
            $postToEdit = $this->em->getRepository(Post::class)->findOneBy(['id' => $_GET['id']]);

            $form = $this->createForm(PostType::class);
            $form->setData($postToEdit);

            if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
                $postData = $form->getData();
                $postedMedia = $form->get('media')->getData();

                foreach($postedMedia as $postedMedium){
                    $media = $this->mediaHandler($postedMedium, $slugger);

                    $media->setPost($postToEdit);
                    $this->em->persist($media);
                    unset($postedMedium);
                }

                if(!$postData->getTitle()){
                    $postData->setTitle($postToEdit->getTitle());
                }
                if(!$postData->getDescription()){
                    $postData->setDescription($postToEdit->getDescription());
                }

                $datetime = new \DateTimeImmutable();
                $postData->setModifiedAt($datetime->setTimezone(new DateTimeZone("Europe/Paris")));

                $this->em->persist($postData);
                $this->em->flush();

                return $this->redirectToRoute('home');
            }

            return $this->render('main/publish.html.twig', [
                'post_form' => $form->createView()
            ]);
        } else {
            return $this->redirectToRoute('home');
        }
    }

    #[Route('/supprimer', name: 'delete', methods: ['GET'])]
    public function delete(): Response
    {
        if($this->getUser()){
            $post = $this->em->getRepository(Post::class)->findOneBy(['id' => $_GET['id']]);
            $media = $post->getMedia();

            foreach($media as $medium){
                $projectDir = $this->getParameter('uploads_directory');
                $fileSystem = new Filesystem();
                $fileSystem->remove($projectDir . '/' . $medium->getPath());

                $this->em->remove($medium);

                unset($medium);
            }

            $this->em->remove($post);
            $this->em->flush();

            $this->addFlash('success', 'Article supprimé avec succès !');
            return $this->redirectToRoute('home');
        } else {
            return $this->redirectToRoute('home');
        }
    }

    #[Route('/supprimer-image', name: 'deleteImg', methods: ['GET'])]
    public function deleteImg(): Response
    {
        if($this->getUser()){
            $media = $this->em->getRepository(Media::class)->findOneBy(['id' => $_GET['id']]);

            $projectDir = $this->getParameter('uploads_directory');
            $fileSystem = new Filesystem();
            $fileSystem->remove($projectDir . '/' . $media->getPath());

            $this->em->remove($media);
            $this->em->flush();

            if($_GET['postId'] == 'fromGallery'){
                return $this->redirectToRoute('gallery');
            } else {
                return $this->redirect('blog?id=' . $_GET['postId']);
            }
        } else {
            return $this->redirectToRoute('home');
        }
    }

    /**
     * @param mixed $postedMedium
     * @param SluggerInterface $slugger
     * @return Media
     */
    public function mediaHandler(mixed $postedMedium, SluggerInterface $slugger): Media
    {
        $media = new Media();
        $originalFilename = pathinfo($postedMedium->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $postedMedium->guessExtension();

        $postedMedium->move($this->getParameter('uploads_directory'), $newFilename);
        $this->imageOptimizer->resize($this->getParameter('uploads_directory').'/'.$newFilename);

        $media->setPath($newFilename);
        return $media;
    }
}
