<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/register', name: 'api_register_', methods: ['POST'])]
class RegisterController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'post', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $pseudo = trim($data['pseudo'] ?? '');
        $email = trim($data['email'] ?? '');
        $plainPassword = $data['password'] ?? '';

        if ('' === $pseudo || '' === $email || '' === $plainPassword) {
            return $this->json(
                ['message' => 'pseudo, email et password sont requis'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($this->userRepository->findOneByPseudo($pseudo)) {
            return $this->json(
                ['message' => 'Ce pseudo est déjà utilisé'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($this->userRepository->findOneByEmail($email)) {
            return $this->json(
                ['message' => 'Cet email est déjà utilisé'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $user = new User();
        $user->setPseudo($pseudo);
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setLastLoginAt(new \DateTime());

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getMessage();
            }
            return $this->json(
                ['message' => implode(' ', $messages)],
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->userRepository->save($user, true);

        return $this->json(
            ['id' => $user->getId(), 'pseudo' => $user->getPseudo(), 'email' => $user->getEmail()],
            Response::HTTP_CREATED
        );
    }
}
