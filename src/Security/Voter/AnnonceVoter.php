<?php
// src/Security/Voter/AnnonceVoter.php
namespace App\Security\Voter;

use App\Entity\Annonce;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AnnonceVoter extends Voter
{
    public const EDIT = 'ANNONCE_EDIT';
    public const DELETE = 'ANNONCE_DELETE';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE], true) && $subject instanceof Annonce;
    }

    protected function voteOnAttribute(string $attribute, $annonce, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!\is_object($user)) return false;

        // Interdit toute modif si finished (sauf admin)
        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);
        if ($annonce->isFinished() && !$isAdmin) return false;

        // Auteur ou admin
        return $annonce->getUser() === $user || $isAdmin;
    }
}
