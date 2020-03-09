<?php

namespace Bsll\ApiBundle\Controller;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\ORM\Cache;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\View;

abstract class BaseController extends AbstractFOSRestController
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var string
     */
    protected $formTypeClass;

    /**
     * @var string
     */
    protected $entityClass;

    public function __construct(
        EntityManagerInterface $entityManager,
        string $entityClass,
        string $formTypeClass
    ) {
        $this->entityManager = $entityManager;
        $this->entityClass = $entityClass;
        $this->formTypeClass = $formTypeClass;
    }

    protected function getEntity(int $id)
    {
        $entity = $this->entityManager->find($this->entityClass, $id);

        if (empty($entity)) {
            throw new NotFoundHttpException(sprintf('Entity (%d) not found', $id));
        }

        return $entity;
    }

    protected function getForm($entity)
    {
        return $this->get('form.factory')->createNamed(null, $this->formTypeClass, $entity, [
            'csrf_protection' => false,
        ]);
    }

    protected function save(Request $request, $entity)
    {
        $form = $this->getForm($entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entity = $form->getData();

            $this->saveEntity($entity);

            return $entity;
        }

        return $form;
    }

    protected function saveEntity($entity)
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->clearCache($this->entityManager->getCache());
    }

    /**
     * @param Request $request
     * @View()
     *
     * @return Response
     * @Rest\Get("")
     */
    public function listAction(Request $request)
    {
        return $this->entityManager->getRepository($this->entityClass)->findAll();
    }

    /**
     * @param Request $request
     * @View()
     *
     * @return Response
     *
     * @throws NotFoundHttpException
     * @Rest\Get("{id}")
     */
    public function getAction(Request $request)
    {
        return $this->getEntity($request->get('id'));
    }

    /**
     * @param Request $request
     * @View(statusCode = 201)
     *
     * @return Response
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @Rest\Post("")
     */
    public function addAction(Request $request)
    {
        $entity = new $this->entityClass();

        return $this->save($request, $entity);
    }

    /**
     * @param Request $request
     * @View()
     *
     * @return Response
     *
     * @throws NotFoundHttpException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @Rest\Post("{id}")
     */
    public function editAction(Request $request)
    {
        $entity = $this->getEntity($request->get('id'));

        return $this->save($request, $entity);
    }

    /**
     * @param Request $request
     * @View()
     *
     * @return Response
     *
     * @throws NotFoundHttpException
     * @Rest\Delete("{id}")
     */
    public function deleteAction(Request $request)
    {
        $entity = $this->getEntity($request->get('id'));
        $this->entityManager->remove($entity);

        return $entity;
    }

    /**
     * @param Cache|null $cache
     */
    protected function clearCache(?Cache $cache)
    {
        if ($cache) {
            $cache->evictEntityRegions();
            $cache->evictCollectionRegions();
            $cache->evictQueryRegions();
        }
    }
}