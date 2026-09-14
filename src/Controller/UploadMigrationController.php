<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UploadMigrationController extends AbstractController
{
    #[Route('/importer-photos', name: 'import_uploads', methods: ['GET', 'POST'])]
    public function import(Request $request): Response
    {
        $source = $this->getParameter('kernel.project_dir').'/uploads-source';
        $destination = $this->getParameter('kernel.project_dir').'/public/uploads';

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('import_uploads', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            if (!is_dir($source)) {
                $this->addFlash('error', 'Le dossier source uploads-source est introuvable.');

                return $this->redirectToRoute('admin_import_uploads');
            }

            if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
                throw new \RuntimeException(sprintf('Impossible de créer le dossier %s.', $destination));
            }

            $copied = 0;
            $skipped = 0;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $relativePath = substr($file->getPathname(), strlen($source) + 1);
                $target = $destination.DIRECTORY_SEPARATOR.$relativePath;
                $targetDirectory = dirname($target);

                if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
                    throw new \RuntimeException(sprintf('Impossible de créer le dossier %s.', $targetDirectory));
                }

                if (file_exists($target)) {
                    ++$skipped;
                    continue;
                }

                if (copy($file->getPathname(), $target)) {
                    ++$copied;
                }
            }

            $this->addFlash('success', sprintf('%d fichier(s) copié(s), %d déjà présent(s).', $copied, $skipped));

            return $this->redirectToRoute('admin_import_uploads');
        }

        return $this->render('admin/import_uploads.html.twig', [
            'sourceExists' => is_dir($source),
            'destinationMounted' => is_dir($destination),
        ]);
    }
}