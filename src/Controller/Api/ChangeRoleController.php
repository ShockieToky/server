<?php

namespace App\Controller\Api;

use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/me/role', name: 'api_me_role_', methods: ['PATCH'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ChangeRoleController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[Route('', name: 'patch', methods: ['PATCH'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $role = trim($data['role'] ?? '');

        $allowed = ['player', 'admin'];
        if (!in_array($role, $allowed, true)) {
            return $this->json(
                ['message' => 'Rôle invalide. Valeurs acceptées : ' . implode(', ', $allowed)],
                Response::HTTP_BAD_REQUEST
            );
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $user->setRole($role);
        $this->userRepository->save($user, true);

        $token = $this->jwtManager->create($user);

        return $this->json(['token' => $token, 'role' => $role]);
    }
}
