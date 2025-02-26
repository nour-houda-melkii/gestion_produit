<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\Produit;
use App\Service\EmailService;

final class CommandeController extends AbstractController
{
    #[Route('/commande', name: 'app_commande')]
    public function index(): Response
    {
        return $this->render('commande/index.html.twig', [
            'controller_name' => 'CommandeController',
        ]);
    }

    #[Route('/cart', name: 'view_cart', methods: ['GET'])]
    public function viewCart(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'You must be logged in to view your cart.');
            return $this->redirectToRoute('app_login');
        }

        $commande = $entityManager->getRepository(Commande::class)->findOneBy([
            'user' => $user,
            'statut' => 'pending',
        ]);

        return $this->render('commande/cart.html.twig', [
            'commande' => $commande,
        ]);
    }

    #[Route('/confirm-purchase', name: 'confirm_purchase', methods: ['POST'])]
public function confirmPurchase(Request $request, EntityManagerInterface $entityManager, EmailService $emailService): Response
{
    // Validate CSRF token
    $submittedToken = $request->request->get('_csrf_token');
    if (!$this->isCsrfTokenValid('confirm_purchase', $submittedToken)) {
        $this->addFlash('error', 'Invalid CSRF token.');
        return $this->redirectToRoute('view_cart');
    }

    $user = $this->getUser();
    if (!$user) {
        $this->addFlash('error', 'You must be logged in to confirm your purchase.');
        return $this->redirectToRoute('app_login');
    }

    $commande = $entityManager->getRepository(Commande::class)->findOneBy([
        'user' => $user,
        'statut' => 'pending',
    ]);

    if (!$commande || $commande->getCommandeLignes()->isEmpty()) {
        $this->addFlash('error', 'Your cart is empty.');
        return $this->redirectToRoute('view_cart');
    }

    // Update the order status
    $commande->setStatut('confirmed');
    $entityManager->flush();

    // Try to send the confirmation email
    try {
        $emailService->sendConfirmationEmail(
            $user->getEmail(),
            'Order Confirmation',
            $this->renderView('emails/order_confirmation.html.twig', [
                'commande' => $commande,
                'user' => $user,
            ])
        );
        $this->addFlash('success', 'Your purchase has been confirmed. A confirmation email has been sent.');
    } catch (\Exception $e) {
        // Log the error (optional)
        // $this->logger->error('Failed to send confirmation email: ' . $e->getMessage());

        // Display a success message even if the email fails
        $this->addFlash('success', 'Your purchase has been confirmed. However, the confirmation email could not be sent.');
    }

    return $this->redirectToRoute('front_list');
}
    #[Route('/cart/add/{productId}', name: 'add_to_cart', methods: ['POST'])]
    public function addToCart(int $productId, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'You must be logged in to add items to your cart.');
            return $this->redirectToRoute('app_login');
        }

        // Fetch the product
        $product = $entityManager->getRepository(Produit::class)->find($productId);
        if (!$product) {
            $this->addFlash('error', 'Product not found.');
            return $this->redirectToRoute('view_cart');
        }

        // Fetch the user's pending order (cart)
        $commande = $entityManager->getRepository(Commande::class)->findOneBy([
            'user' => $user,
            'statut' => 'pending',
        ]);

        // If no pending order exists, create one
        if (!$commande) {
            $commande = new Commande();
            $commande->setUser($user);
            $commande->setStatut('pending');
            $commande->setTotal(0);
            $entityManager->persist($commande);
        }

        // Check if the product is already in the cart
        $existingLigne = null;
        foreach ($commande->getCommandeLignes() as $ligne) {
            if ($ligne->getProduit()->getId() === $productId) {
                $existingLigne = $ligne;
                break;
            }
        }

        if ($existingLigne) {
            // If the product is already in the cart, increment the quantity
            $existingLigne->setQuantity($existingLigne->getQuantity() + 1);
        } else {
            // If the product is not in the cart, create a new cart item
            $ligne = new CommandeLigne();
            $ligne->setProduit($product);
            $ligne->setQuantity(1);
            $ligne->setCommande($commande);
            $entityManager->persist($ligne);
        }

        // Update the total price of the order
        $total = 0;
        foreach ($commande->getCommandeLignes() as $ligne) {
            $total += $ligne->getProduit()->getPrice() * $ligne->getQuantity();
        }
        $commande->setTotal($total);

        $entityManager->flush();

        $this->addFlash('success', 'Product added to cart.');
        return $this->redirectToRoute('view_cart');
    }

    #[Route('/cart/update/{id}', name: 'update_cart_item', methods: ['POST'])]
    public function updateCartItem(CommandeLigne $ligne, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $change = $data['change'];

        $newQuantity = $ligne->getQuantity() + $change;
        if ($newQuantity < 1) {
            return new JsonResponse(['error' => 'Quantity cannot be less than 1'], 400);
        }

        $ligne->setQuantity($newQuantity);
        $em->flush();

        $lineTotal = $ligne->getProduit()->getPrice() * $ligne->getQuantity();
        $cartTotal = $ligne->getCommande()->getTotal();
        $cartCount = $ligne->getCommande()->getCommandeLignes()->count();

        return new JsonResponse([
            'quantity' => $ligne->getQuantity(),
            'lineTotal' => $lineTotal,
            'cartTotal' => $cartTotal,
            'cartCount' => $cartCount,
        ]);
    }

    #[Route('/cart/remove/{id}', name: 'remove_cart_item', methods: ['DELETE'])]
    public function removeCartItem(CommandeLigne $ligne, EntityManagerInterface $em): JsonResponse
    {
        try {
            $em->remove($ligne);
            $em->flush();

            $cartTotal = $ligne->getCommande()->getTotal();
            $cartCount = $ligne->getCommande()->getCommandeLignes()->count();

            return new JsonResponse([
                'cartTotal' => $cartTotal,
                'cartCount' => $cartCount,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'An error occurred while removing the item.'], 500);
        }
    }

    
}