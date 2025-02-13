<?php

namespace App\Controller;
use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/produit')]
final class ProduitController extends AbstractController
{
    #[Route(name: 'app_produit_index', methods: ['GET'])]
    public function index(ProduitRepository $produitRepository): Response
    {
        return $this->render('produit/index.html.twig', [
            'produits' => $produitRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_produit_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $produit = new Produit();
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                // Generate a unique name for the file
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();

                try {
                    // Move the file to the configured directory (ensure the directory exists)
                    $imageFile->move(
                        $this->getParameter('images_directory'),  // Defined in services.yaml
                        $newFilename
                    );

                    // Store only the filename in the database
                    $produit->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'enregistrement de l\'image.');
                }
            }
            
            // Contrôle de saisie pour le prix
            if ($form->isSubmitted() && $form->isValid()) {
    $prix = $produit->getPrice();
    
    if ($prix < 0) {
        $this->addFlash('error', 'Le prix ne peut pas être négatif.');
        return $this->redirectToRoute('app_produit_new');
    }

    if ($prix < 0.50 || $prix > 10000) {
        $this->addFlash('error', 'Le prix doit être compris entre 0,50 DT et 10000 DT.');
        return $this->redirectToRoute('app_produit_new');
    }

    $entityManager->persist($produit);
    $entityManager->flush();

    return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
}

        }

        return $this->render('produit/new.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_produit_show_back', methods: ['GET'])]
    public function show(Produit $produit): Response
    {
        return $this->render('produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_produit_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Contrôle de saisie pour le prix
            $prix = $produit->getPrice();
            if ($prix < 0.50 || $prix > 1000) {
                $this->addFlash('error', 'Le prix doit être compris entre 0,50DT et 1000 DT');
                return $this->redirectToRoute('app_produit_edit', ['id' => $produit->getId()]);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('produit/edit.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_produit_delete', methods: ['POST'])]
    public function delete(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$produit->getId(), $request->get('_token'))) {
            $entityManager->remove($produit);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);

    }

    #[Route('/front', name: 'app_produit_show', methods: ['GET', 'POST'])]
    public function showproduitfront(ProduitRepository $produitRepository): Response
    {
        $produits = $produitRepository->findAll();
        return $this->render('produit/indexClient.html.twig',
         [
            'produits' => $produits,
        ]
    );
    }
}