<?php

namespace App\Creator;

use App\Entity\Alias;
use App\Entity\User;
use App\Event\AliasCreatedEvent;
use App\Event\RandomAliasCreatedEvent;
use App\Factory\AliasFactory;

/**
 * Class AliasCreator.
 */
class AliasCreator extends AbstractCreator
{
    /**
     * @param User        $user
     * @param string|null $localPart
     *
     * @return Alias
     *
     * @throws \App\Exception\ValidationException
     */
    public function create(User $user, ?string $localPart): Alias
    {
        $localPart = (isset($localPart)) ? strtolower($localPart) : null;
        $alias = AliasFactory::create($user, $localPart);

        $this->eventDispatcher->dispatch(AliasCreatedEvent::NAME, new AliasCreatedEvent($alias));
        if (null === $localPart) {
            $this->eventDispatcher->dispatch(RandomAliasCreatedEvent::NAME, new RandomAliasCreatedEvent($alias));
        }

        $this->validate($alias, ['Default', 'unique']);
        $this->save($alias);

        return $alias;
    }
}
