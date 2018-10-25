<?php

namespace App\Controller;

use App\Creator\VoucherCreator;
use App\Entity\Alias;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Form\Model\AliasCreate;
use App\Form\Model\PasswordChange;
use App\Form\Model\VoucherCreate;
use App\Form\CustomAliasCreateType;
use App\Form\RandomAliasCreateType;
use App\Form\PasswordChangeType;
use App\Form\VoucherCreateType;
use App\Handler\AliasHandler;
use App\Handler\VoucherHandler;
use App\Helper\PasswordUpdater;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class StartController.
 */
class StartController extends Controller
{
    /**
     * @var AliasHandler
     */
    private $aliasHandler;
    /**
     * @var PasswordUpdater
     */
    private $passwordUpdater;
    /**
     * @var VoucherHandler
     */
    private $voucherHandler;
    /**
     * @var VoucherCreator
     */
    private $voucherCreator;

    /**
     * StartController constructor.
     *
     * @param AliasHandler    $aliasHandler
     * @param PasswordUpdater $passwordUpdater
     * @param VoucherHandler  $voucherHandler
     * @param VoucherCreator  $voucherCreator
     */
    public function __construct(AliasHandler $aliasHandler, PasswordUpdater $passwordUpdater, VoucherHandler $voucherHandler, VoucherCreator $voucherCreator)
    {
        $this->aliasHandler = $aliasHandler;
        $this->passwordUpdater = $passwordUpdater;
        $this->voucherHandler = $voucherHandler;
        $this->voucherCreator = $voucherCreator;
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function indexAction(Request $request)
    {
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('Start/index_anonymous.html.twig');
        }

        /** @var User $user */
        $user = $this->getUser();

        $voucherCreateForm = $this->createForm(
            VoucherCreateType::class,
            new VoucherCreate(),
            [
                'action' => $this->generateUrl('index'),
                'method' => 'post',
            ]
        );

        $randomAliasCreateForm = $this->createForm(
            RandomAliasCreateType::class,
            new AliasCreate(),
            [
                'action' => $this->generateUrl('index'),
                'method' => 'post',
            ]
        );

        $aliasCreate = new AliasCreate();
        $customAliasCreateForm = $this->createForm(
            CustomAliasCreateType::class,
            $aliasCreate,
            [
                'action' => $this->generateUrl('index'),
                'method' => 'post',
            ]
        );

        $passwordChange = new PasswordChange();
        $passwordChangeForm = $this->createForm(
            PasswordChangeType::class,
            $passwordChange,
            [
                'action' => $this->generateUrl('index'),
                'method' => 'post',
            ]
        );

        if ('POST' === $request->getMethod()) {
            $voucherCreateForm->handleRequest($request);
            $randomAliasCreateForm->handleRequest($request);
            $customAliasCreateForm->handleRequest($request);
            $passwordChangeForm->handleRequest($request);

            if ($voucherCreateForm->isSubmitted() && $voucherCreateForm->isValid()) {
                $this->createVoucher($request, $user);
            } elseif ($randomAliasCreateForm->isSubmitted() && $randomAliasCreateForm->isValid()) {
                $this->createRandomAlias($request, $user);
            } elseif ($customAliasCreateForm->isSubmitted() && $customAliasCreateForm->isValid()) {
                $this->createCustomAlias($request, $user, $aliasCreate->alias);
            } elseif ($passwordChangeForm->isSubmitted() && $passwordChangeForm->isValid()) {
                $this->changePassword($request, $user, $passwordChange->newPassword);
            }
        }

        $aliasRepository = $this->get('doctrine')->getRepository('App:Alias');
        $aliasesRandom = $aliasRepository->findByUser($user, true);
        $aliasesCustom = $aliasRepository->findByUser($user, false);
        $vouchers = $this->voucherHandler->getVouchersByUser($user);

        return $this->render(
            'Start/index.html.twig',
            [
                'user' => $user,
                'user_domain' => $user->getDomain(),
                'alias_creation_random' => $this->aliasHandler->checkAliasLimit($aliasesRandom, true),
                'alias_creation_custom' => $this->aliasHandler->checkAliasLimit($aliasesCustom),
                'aliases_custom' => $aliasesCustom,
                'aliases_random' => $aliasesRandom,
                'vouchers' => $vouchers,
                'voucher_form' => $voucherCreateForm->createView(),
                'random_alias_form' => $randomAliasCreateForm->createView(),
                'custom_alias_form' => $customAliasCreateForm->createView(),
                'password_form' => $passwordChangeForm->createView(),
            ]
        );
    }

    /**
     * @param Request $request
     * @param User    $user
     */
    private function createVoucher(Request $request, User $user)
    {
        if ($this->isGranted('ROLE_SUPPORT')) {
            try {
                $this->voucherCreator->create($user);

                $request->getSession()->getFlashBag()->add('success', 'flashes.voucher-creation-successful');
            } catch (ValidationException $e) {
                // Should not thrown
            }
        }
    }

    /**
     * @param Request $request
     * @param User    $user
     */
    private function createRandomAlias(Request $request, User $user)
    {
        try {
            if ($this->aliasHandler->create($user) instanceof Alias) {
                $request->getSession()->getFlashBag()->add('success', 'flashes.random-alias-creation-successful');
            }
        } catch (ValidationException $e) {
            $request->getSession()->getFlashBag()->add('error', $e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @param User    $user
     * @param string  $alias
     */
    private function createCustomAlias(Request $request, User $user, string $alias)
    {
        try {
            if ($this->aliasHandler->create($user, $alias) instanceof Alias) {
                $request->getSession()->getFlashBag()->add('success', 'flashes.custom-alias-creation-successful');
            }
        } catch (ValidationException $e) {
            $request->getSession()->getFlashBag()->add('error', $e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @param User    $user
     * @param string  $password
     */
    private function changePassword(Request $request, User $user, string $password)
    {
        $user->setPlainPassword($password);

        $this->passwordUpdater->updatePassword($user);

        $this->getDoctrine()->getManager()->flush();

        $request->getSession()->getFlashBag()->add('success', 'flashes.password-change-successful');
    }
}
