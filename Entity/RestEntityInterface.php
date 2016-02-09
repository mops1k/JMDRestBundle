<?php

namespace JMD\RestBundle\Entity;


use Doctrine\ORM\QueryBuilder;

interface RestEntityInterface
{
    /**
     * @param array $order
     * @return QueryBuilder
     */
    public function findAllArray(array $order = []);

    /**
     * @param int|string $id
     * @return array|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneArray($id);
}