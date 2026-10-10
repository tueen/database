<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\Contact;
use Tueen\Database\Exception\RecordNotFoundException;

/**
 * @extends EntityRepository<Contact>
 */
class ContactRepository extends EntityRepository
{
    public function saveContact(
        Chat|int $user,
        string $phoneNumber,
        string $firstName,
        ?string $lastName = null,
        ?string $vcard = null,
    ): Contact {
        $em = $this->getEntityManager();
        $chatEntity = $user instanceof Chat ? $user : $em->find(Chat::class, $user);
        if ($chatEntity === null) {
            throw RecordNotFoundException::forEntity(Chat::class, is_int($user) ? $user : $user->id);
        }

        $cleanPhone = (string) preg_replace('/\D+/', '', $phoneNumber);
        $contact = $this->find($chatEntity->id);

        if ($contact !== null) {
            $contact->phoneNumber = $cleanPhone;
            $contact->firstName = $firstName;
            $contact->lastName = $lastName;
            $contact->vcard = $vcard;
        } else {
            $contact = new Contact(
                user: $chatEntity,
                phoneNumber: $cleanPhone,
                firstName: $firstName,
                lastName: $lastName,
                vcard: $vcard,
            );
            $em->persist($contact);
        }

        $em->flush();
        return $contact;
    }

    public function findByPhoneNumber(string $phoneNumber): ?Contact
    {
        $cleanPhone = (string) preg_replace('/\D+/', '', $phoneNumber);
        return $this->findOneBy(['phoneNumber' => $cleanPhone]);
    }

    public function findByChat(Chat|int $chat): ?Contact
    {
        $chatId = $chat instanceof Chat ? $chat->id : $chat;
        return $this->find($chatId);
    }
}
