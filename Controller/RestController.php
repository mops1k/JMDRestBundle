<?php

namespace JMD\RestBundle\Controller;

use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class RestController
 * @package JMD\RestBundle\Controller
 */
class RestController extends ApiController
{
    /**
     * @Route(
     *     path="/{bundleName}/{entityName}",
     *     name="rest_get_entity_list",
     *     methods={"GET"},
     *     options={"expose"=true}
     * )
     *
     * @param $entityName
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction($bundleName, $entityName, Request $request)
    {
        $manager = $this->getDoctrine()->getManager();
        $entityName = ucfirst($entityName);
        $entityAlias = $bundleName.':'.$entityName;

        $checkEntity = $this->checkEntity($entityName, $bundleName);
        if(0 !== $checkEntity) {
            $data = $checkEntity;
            return $this->view($data, $data['status']);
        }

        try {
            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = $manager->getRepository($entityAlias)->findAllArray();

            $queryBuilder->orderBy($queryBuilder->getRootAlias().'.'.$request->get('field', 'id'), $request->get('direction', 'DESC'));

            $query = $queryBuilder->getQuery();

            if($request->get('page') !== null) {
                $page = $request->get('page', 1);
                $onPage = $request->get('on_page', 10);

                $firstResult = $page * $onPage - $onPage;
                $pagination = new Paginator($queryBuilder->setFirstResult($firstResult)->setMaxResults($onPage));

                $query = $pagination->getQuery();

                $data['total_pages'] = ceil($pagination->count()/$onPage);
            }

            $data['status'] = JsonResponse::HTTP_OK;
            $data['data'] = $query->getArrayResult();
            $code = JsonResponse::HTTP_OK;
        } catch (QueryException $e) {
            $data = [
                'status' => $e->getCode(),
                'message' => $e->getMessage()
            ];
            $code = JsonResponse::HTTP_NOT_FOUND;
        }

        return $this->view($data, $code);
    }

    /**
     * @Route(
     *     path="/{bundleName}/{entityName}/{id}",
     *     name="rest_get_entity_item",
     *     methods={"GET"},
     *     options={"expose"=true}
     * )
     *
     * @param $entityName
     * @param $id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAction($bundleName, $entityName, $id)
    {
        $manager = $this->getDoctrine()->getManager();
        $entityName = ucfirst($entityName);
        $entityAlias = $bundleName.':'.$entityName;

        $checkEntity = $this->checkEntity($entityName, $bundleName);
        if(0 !== $checkEntity) {
            $data = $checkEntity;
            return $this->view($data, $data['status']);
        }

        $data = $manager->getRepository($entityAlias)->findOneArray($id);

        $code = JsonResponse::HTTP_OK;

        if($data == null) {
            $code = JsonResponse::HTTP_NOT_FOUND;
            $data['message'] = sprintf(
                'Item "%" in entity "%s" are not found (is it published?).',
                $id,
                $entityName
            );
        }

        $data = [
            'status' => $code,
            'data'   => $data
        ];

        return $this->view($data, $code);
    }

    /**
     * @Route(
     *     path="/{bundleName}/{entityName}/{id}",
     *     name="rest_update_entity_item",
     *     methods={"PUT"},
     *     options={"expose"=true}
     * )
     *
     * @param $bundleName
     * @param $entityName
     * @param $id
     * @param Request $request
     * @return JsonResponse
     * @throws HttpException
     */
    public function updateAction($bundleName, $entityName, $id, Request $request)
    {
        if (0 !== strpos($request->headers->get('Content-Type'), 'application/json')) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST);
        }

        $manager = $this->getDoctrine()->getManager();
        $entityName = ucfirst($entityName);
        $entityAlias = $bundleName.':'.$entityName;

        $data = json_decode($request->getContent(), true);

        ${$entityName} = $manager->getRepository($entityAlias)->find($id);

        if (${$entityName} === null) {
            $data = [
                'status' => JsonResponse::HTTP_NOT_FOUND,
                'message'=> sprintf('Not found item #%s in entity "%s"', $id, $entityName)
            ];
            return $this->view($data, $data['status']);
        }

        $data = $this->updateOrCreateObject($bundleName, ${$entityName}, $data);

        return $this->view($data, $data['status']);
    }

    /**
     * @Route(
     *     path="/{bundleName}/{entityName}/{id}/x",
     *     name="rest_x_update_entity_item",
     *     methods={"PUT"},
     *     options={"expose"=true}
     * )
     *
     * @param $bundleName
     * @param $entityName
     * @param $id
     * @param Request $request
     * @return JsonResponse
     * @throws HttpException
     */
    public function xUpdateAction($bundleName, $entityName, $id, Request $request)
    {
        $manager = $this->getDoctrine()->getManager();
        $entityName = ucfirst($entityName);
        $entityAlias = $bundleName.':'.$entityName;

        $data = [
            $request->get('name') => $request->get('value')
        ];

        ${$entityName} = $manager->getRepository($entityAlias)->find($id);

        if (${$entityName} === null) {
            $data = [
                'status' => JsonResponse::HTTP_NOT_FOUND,
                'message'=> sprintf('Not found item #%s in entity "%s"', $id, $entityName)
            ];
            return $this->view($data, $data['status']);
        }

        $data = $this->updateOrCreateObject($bundleName, ${$entityName}, $data);

        return $this->view($data, $data['status']);
    }

    /**
     * @Route(
     *     path="/{bundleName}/{entityName}/{id}",
     *     name="rest_delete_entity_item",
     *     methods={"DELETE"},
     *     options={"expose"=true}
     * )
     *
     * @param $bundleName
     * @param $entityName
     * @param $id
     * @return JsonResponse
     */
    public function deleteAction($bundleName, $entityName, $id)
    {
        $entityAlias = $bundleName.':'.$entityName;

        ${$entityName} = $this->getDoctrine()->getRepository($entityAlias)->find($id);

        if(${$entityName} === null) {
            throw new NotFoundHttpException(
                sprintf(
                    "Entity '%s' with id %s not found and can not be deleted",
                    $entityName,
                    $id
                ));
        }

        $this->getDoctrine()->getManager()->remove(${$entityName});
        $this->getDoctrine()->getManager()->flush();

        return new JsonResponse([
            'status' => JsonResponse::HTTP_OK,
            'id'    => $id
        ], JsonResponse::HTTP_OK);
    }

    /**
     * @Route(
     *     path="/{bundleName}/{entityName}",
     *     name="rest_entity_add_item",
     *     methods={"POST"},
     *     options={"expose"=true}
     * )
     *
     * @param $bundleName
     * @param $entityName
     * @param Request $request
     * @return JsonResponse
     * @throws \Doctrine\ORM\ORMException
     */
    public function newAction($bundleName, $entityName, Request $request)
    {
        $data = json_decode($request->getContent(), true);

        if (0 !== strpos($request->headers->get('Content-Type'), 'application/json')) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST);
        }

        $namespace = $this->getDoctrine()->getAliasNamespace($bundleName);
        $class = $namespace.'\\'.$entityName;
        ${$entityName} = new $class;

        $data = $this->updateOrCreateObject($bundleName, ${$entityName}, $data);

        return $this->view($data, $data['status']);
    }
}
