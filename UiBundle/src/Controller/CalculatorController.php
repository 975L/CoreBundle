<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller;

use c975L\UiBundle\Repository\FormRepository;
use c975L\UiBundle\Service\ExpressionEvaluator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

// Recomputes a calculator Form's outputs from the values currently in its inputs - deliberately apart from FormController: this is a read, answered on GET with no CSRF token, no session, no rate limiter and no flash, and a slider being dragged calls it dozens of times a minute (see assets/js/calculator.js, which debounces). Kept server-side rather than duplicated in JavaScript so a formula an admin typed has exactly one implementation
class CalculatorController extends AbstractController
{
    public function __construct(
        private readonly FormRepository $formRepository,
        private readonly ExpressionEvaluator $expressionEvaluator,
    ) {
    }

    #[Route('/form/{name}/compute', name: 'ui_form_compute', methods: ['GET'])]
    public function compute(string $name, Request $request): JsonResponse
    {
        $uiForm = $this->formRepository->findOneBy(['name' => $name]);
        if (null === $uiForm || !$uiForm->isCalculator() || !$uiForm->isEnabled()) {
            throw new NotFoundHttpException(sprintf('No calculator Form named "%s"', $name));
        }

        // Every value is read as a plain string keyed by the field's own name and turned into a float by the evaluator, which falls back to the field's default then to 0 - nothing here trusts the query string to be complete or numeric
        $results = $this->expressionEvaluator->compute($uiForm, $request->query->all());

        // Answered as "private, no-store": the numbers belong to whoever typed them, and a shared cache holding one visitor's result would serve it to the next
        return new JsonResponse($results, headers: ['Cache-Control' => 'private, no-store']);
    }
}
