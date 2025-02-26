<?php

namespace App\Controller;
use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use App\Repository\CategoryRepository;
use App\Entity\Category;
use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\Favori;
use App\Entity\Commentaire;
use Knp\Component\Pager\PaginatorInterface; // Add this line


#[Route('/produit')]
class ProduitController extends AbstractController
{
    #[Route('/front', name: 'front_list', methods: ['GET'])]
    public function listProducts(Request $request, ManagerRegistry $doctrine, PaginatorInterface $paginator): Response
    {
        $categoryId = $request->query->get('category');
        $searchQuery = $request->query->get('search', ''); // Get the search query from the request
    
        $produitRepository = $doctrine->getRepository(Produit::class);
        $categoryRepository = $doctrine->getRepository(Category::class);
    
        // Create a query builder for products
        $queryBuilder = $produitRepository->createQueryBuilder('p');
    
        // Apply category filter if selected
        if ($categoryId) {
            $category = $categoryRepository->find($categoryId);
            $queryBuilder->andWhere('p.Category = :category')
                          ->setParameter('category', $category);
        }
    
        // Apply search filter if search query is provided
        if ($searchQuery) {
            $queryBuilder->andWhere('p.name LIKE :searchQuery OR p.desciption LIKE :searchQuery')
                          ->setParameter('searchQuery', '%' . $searchQuery . '%');
        }
    
        // Paginate the results (6 products per page)
        $produits = $paginator->paginate(
            $queryBuilder->getQuery(), // Query to paginate
            $request->query->getInt('page', 1), // Current page number, default to 1
            3 // Number of items per page
        );
    
        return $this->render('produit/indexClient.html.twig', [
            'produits' => $produits,
            'categories' => $categoryRepository->findAll(),
            'selectedCategory' => $categoryId,
            'searchQuery' => $searchQuery, // Pass the search query to the template
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
// src/Controller/ProduitController.php

#[Route('/{id}', name: 'app_produit_delete', methods: ['POST'])]
public function delete(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
{
    // Vérifier le token CSRF
    if ($this->isCsrfTokenValid('delete'.$produit->getId(), $request->request->get('_token'))) {
        // Vérifier si le produit est utilisé dans des commandes
        if ($produit->getCommandeLignes()->count() > 0) {
            // Afficher un message d'erreur
            $this->addFlash('error', 'Ce produit ne peut pas être supprimé car il est utilisé dans des commandes.');
        } else {
            // Supprimer le produit
            $entityManager->remove($produit);
            $entityManager->flush();

            // Afficher un message de succès
            $this->addFlash('success', 'Le produit a été supprimé avec succès.');
        }
    }

    // Rediriger vers la page d'index des produits
    return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
}
    #[Route('/search', name: 'produit_search', methods: ['GET'])]
public function search(Request $request, ProduitRepository $produitRepository): JsonResponse
{
    $searchQuery = $request->query->get('query', '');
    $produits = $produitRepository->findBySearchQuery($searchQuery);

    $results = [];
    foreach ($produits as $produit) {
        $results[] = [
            'id' => $produit->getId(),
            'name' => $produit->getName(),
            'image' => $produit->getImage(),
            'price' => $produit->getPrice(),
            'description' => $produit->getDesciption(),
            'quantity' => $produit->getQuantity(),
        ];
    }

    return $this->json($results);
} 

#[Route('/add-to-cart/{id}', name: 'add_to_cart', methods: ['POST'])]
public function addToCart(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
{
    // Vérifier le token CSRF
    $submittedToken = $request->request->get('_token');
    if (!$this->isCsrfTokenValid('add_to_cart' . $produit->getId(), $submittedToken)) {
        $this->addFlash('error', 'Invalid CSRF token.');
        return $this->redirectToRoute('front_list');
    }

    // Vérifier si l'utilisateur est connecté
    $user = $this->getUser();
    if (!$user) {
        $this->addFlash('error', 'You must be logged in to add products to the cart.');
        return $this->redirectToRoute('app_login');
    }

    // Vérifier si le produit est en stock
    if ($produit->getQuantity() < 1) {
        $this->addFlash('error', 'This product is out of stock.');
        return $this->redirectToRoute('front_list');
    }

    // Trouver ou créer une commande en attente pour l'utilisateur
    $commande = $entityManager->getRepository(Commande::class)->findOneBy([
        'user' => $user,
        'statut' => 'pending',
    ]);

    if (!$commande) {
        $commande = new Commande();
        $commande->setUser($user);
        $commande->setStatut('pending');
        $commande->setDateCommande(new \DateTime()); // Ensure the date is set
        $entityManager->persist($commande);
    }

    // Créer une nouvelle ligne de commande
    $ligne = new CommandeLigne();
    $ligne->setCommande($commande);
    $ligne->setProduit($produit);
    $ligne->setQuantity(1); // Quantité par défaut

    // Mettre à jour le stock du produit
    $produit->setQuantity($produit->getQuantity() - 1);

    // Enregistrer les modifications en base de données
    $entityManager->persist($ligne);
    $entityManager->flush();

    // Ajouter un message de succès
    $this->addFlash('success', 'Product added to cart!');
    return $this->redirectToRoute('front_list');
}

// src/Controller/ProduitController.php
#[Route('/toggle-favorite/{id}', name: 'toggle_favorite', methods: ['POST'])]
public function toggleFavorite(Produit $produit, EntityManagerInterface $entityManager): Response
{
    $user = $this->getUser();
    if (!$user) {
        $this->addFlash('error', 'You must be logged in to add products to favorites.');
        return $this->redirectToRoute('app_login');
    }

    $favori = $entityManager->getRepository(Favori::class)->findOneBy([
        'user' => $user,
        'produit' => $produit,
    ]);

    if ($favori) {
        // Remove from favorites
        $entityManager->remove($favori);
        $this->addFlash('success', 'Product removed from favorites.');
    } else {
        // Add to favorites
        $favori = new Favori();
        $favori->setUser($user);
        $favori->setProduit($produit);
        $entityManager->persist($favori);
        $this->addFlash('success', 'Product added to favorites.');
    }

    $entityManager->flush();
    return $this->redirectToRoute('front_list');
}
#[Route('/produit/{id}/comment', name: 'produit_comment', methods: ['POST'])]
public function addComment(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
{
    $user = $this->getUser();
    if (!$user) {
        $this->addFlash('error', 'You must be logged in to add a comment.');
        return $this->redirectToRoute('app_login');
    }

    $content = $request->request->get('content');
    if (empty($content)) {
        $this->addFlash('error', 'Comment cannot be empty.');
        return $this->redirectToRoute('front_list');
    }

    $commentaire = new Commentaire();
    $commentaire->setContent($content);
    $commentaire->setProduit($produit);
    $commentaire->setUser($user);
    $commentaire->setCreatedAt(new \DateTime());

    $entityManager->persist($commentaire);
    $entityManager->flush();

    $this->addFlash('success', 'Comment added successfully.');
    return $this->redirectToRoute('front_list');
}  
#[Route('/comment/{id}/delete', name: 'comment_delete', methods: ['POST'])]
public function deleteComment(Commentaire $commentaire, EntityManagerInterface $entityManager): Response
{
    $user = $this->getUser();
    if (!$user || $user !== $commentaire->getUser()) {
        $this->addFlash('error', 'You cannot delete this comment.');
        return $this->redirectToRoute('front_list');
    }

    $entityManager->remove($commentaire);
    $entityManager->flush();

    $this->addFlash('success', 'Comment deleted successfully.');
    return $this->redirectToRoute('front_list');
}

}

    

  

