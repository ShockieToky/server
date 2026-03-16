<?php

namespace App\Controller\Api;

use App\Security\UserProvider;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

#[Route('/api/login', name: 'api_login_', methods: ['POST'])]
class LoginController extends AbstractController
{
    public function __construct(
        private readonly UserProvider $userProvider,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[Route('', name: 'post', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if ('' === $username || '' === $password) {
            return $this->json(
                ['message' => 'username et password sont requis'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $user = $this->userProvider->loadUserByIdentifier($username);
        } catch (UserNotFoundException) {
            return $this->json(
                ['message' => 'Identifiants incorrects'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(
                ['message' => 'Identifiants incorrects'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $token = $this->jwtManager->create($user);

        return $this->json(['token' => $token], Response::HTTP_OK);
    }
}
