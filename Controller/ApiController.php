<?php

namespace JMD\RestBundle\Controller;

use Doctrine\Common\Util\Inflector;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use JMD\RestBundle\Entity\RestEntityInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class ApiController extends Controller
{
    public function view($data, $code = JsonResponse::HTTP_OK)
    {
        return new JsonResponse($data, $code);
    }

    /**
     * @param $entityName
     * @param string $aliasNamespace
     * @return array|int
     */
    public function checkEntity($entityName, $aliasNamespace)
    {
        $namespace = $this->getDoctrine()->getAliasNamespace($aliasNamespace);
        $class = $namespace.'\\'.$entityName;

        $entityAlias = $aliasNamespace.':'.$entityName;

        if (!class_exists($class)) {
            return [
                'status' => JsonResponse::HTTP_NOT_FOUND,
                'message' => sprintf('Entity "%s" not found.', $entityName)
            ];
        }

        /** @var EntityManager $manager */
        $manager = $this->getDoctrine()->getManager();

        $repositoryClassName = $manager->getClassMetadata($entityAlias)->customRepositoryClassName;
        if (!class_exists($repositoryClassName)) {
            return [
                'status' => JsonResponse::HTTP_NOT_FOUND,
                'message' => sprintf('Entity repository "%s" not found.', $repositoryClassName)
            ];
        }

        $repositoryClass = new $repositoryClassName($manager, $manager->getClassMetadata($entityAlias));
        if (!$repositoryClass instanceof RestEntityInterface) {
            return [
                'status' => JsonResponse::HTTP_FAILED_DEPENDENCY,
                'message' => sprintf('Entity repository "%s" must implement "%s".', $repositoryClassName, RestEntityInterface::class)
            ];
        }

        return 0;
    }

    public function updateOrCreateObject($bundleName, $object, $data)
    {
        $manager = $this->getDoctrine()->getManager();
        $reflectionClass = new \ReflectionClass($object);
        $entityName = ucfirst($reflectionClass->getShortName());
        $entityAlias = $bundleName.':'.$entityName;

        ${$entityName} = $object;

        $metadata = $manager->getClassMetadata($entityAlias);
        $assocFields = $manager->getClassMetadata($entityAlias)->getAssociationNames();

        $assoc = [];

        foreach ($metadata->getAssociationMappings() as $fieldName => $mapping) {
            $assoc[$fieldName] = $mapping['type'];
        }

        foreach ($data as $property => $value) {
            $method = null;

            if (isset($assoc[$property]) && ($assoc[$property] & ClassMetadataInfo::TO_MANY)) {
                $type = 'add';
                $methodName = $type . Inflector::classify($property);
                if (in_array('add', array("add", "remove"))) {
                    $methodName = Inflector::singularize($methodName);
                }
                $method = $methodName;
            }

            if ($method === null) {
                $method = 'set' . Inflector::classify($property);
            }

            if (!method_exists(${$entityName}, $method)) {
                return [
                    'status' => JsonResponse::HTTP_BAD_REQUEST,
                    'message' => sprintf("Entity '%s' has no property '%s'", $entityName, $property)
                ];
            }

            if (!in_array($property, $assocFields)) {
                ${$entityName}->$method($value);
                continue;
            }


            if ($metadata->isCollectionValuedAssociation($property)) {
                $assocClass = $metadata->getAssociationTargetClass($property);
                foreach ($value as $id) {
                    $assocEntity = $manager->getRepository($assocClass)->find($id);
                    ${$entityName}->$method($assocEntity);
                }
                continue;
            }
            if(!$metadata->isAssociationInverseSide($property)) {
                $assocClass = $metadata->getAssociationTargetClass($property);
                $assocEntity = $manager->getRepository($assocClass)->find($value);
                ${$entityName}->$method($assocEntity);
            }
        }

        $manager->persist(${$entityName});
        $manager->flush();

        return [
            'status' => JsonResponse::HTTP_OK,
            'data'  => $data,
            'url'   => $this->generateUrl('rest_get_entity_item', [
                'bundleName' => $bundleName,
                'entityName' => $entityName,
                'id'         => ${$entityName}->getId()
            ])
        ];
    }
}
