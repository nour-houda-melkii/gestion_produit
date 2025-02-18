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
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use App\Repository\CategoryRepository;
use App\Entity\Category;

#[Route('/produit')]
class ProduitController extends AbstractController
{
    #[Route('/front', name: 'front_list', methods: ['GET'])]
    public function listProducts(Request $request, ManagerRegistry $doctrine): Response
    {
        $categoryId = $request->query->get('category');
        $produitRepository = $doctrine->getRepository(Produit::class);
        $categoryRepository = $doctrine->getRepository(Category::class);

        if ($categoryId) {
            $category = $categoryRepository->find($categoryId);
            $produits = $produitRepository->findBy(['Category' => $category]);
        } else {
            $produits = $produitRepository->findAll();
        }

        return $this->render('produit/indexClient.html.twig', [
            'produits' => $produits,
            'categories' => $categoryRepository->findAll(),
            'selectedCategory' => $categoryId
        ]);
    }

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
            $file = $form->get('image')->getData();
            if ($file) {
                $filename = md5(uniqid()) . '.' . $file->guessExtension();

                try {
                    $file->move(
                        $this->getParameter('images_directory'),  // Directory defined in parameters
                        $filename
                    );
                    $produit->setImage($filename);
                } catch (\Exception $e) {
                    // Handle the exception if something goes wrong
                    $this->addFlash('error', 'Failed to upload image.');
                }
            }

            $entityManager->persist($produit);
            $entityManager->flush();

            return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('produit/new.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/front', name: 'app_produit_show', methods: ['GET'])]
    public function showproduitfront(ProduitRepository $produitRepository): Response
    {
        $produits = $produitRepository->findAll();
    return $this->render('produit/indexClient.html.twig', [
        'produits' => $produits,
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
    

  
}