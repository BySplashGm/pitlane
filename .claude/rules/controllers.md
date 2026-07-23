---
description: Controller conventions — thin actions, routing, validation, authorization
globs: ["src/Controller/**"]
alwaysApply: false
---

# Controllers

Pitlane renders a Twig UI, so a controller can hold a few related actions for one resource — a `ServerController` with `index`, `show`, `start`, `stop` is fine. Keep each class focused on one resource and split it once it sprawls past a handful of actions.

## Keep Actions Thin

An action does three things and nothing more: pull what it needs off the `Request`, hand the actual work to an injected service or repository, and return a `Response` (a `render()`, a redirect, or `JsonResponse`). Business logic, orchestration, and anything touching Docker or a process live in a service, not the controller.

## Routes

- Declare routes with `#[Route]` — on the method, or on the class for a shared prefix — and always set a name and `methods`.
- Name them `app_<resource>_<action>` (`app_server_index`, `app_login`), matching the existing security routes.

## AbstractController

Extend `AbstractController` only when a shortcut (`render`, `redirectToRoute`, `denyAccessUnlessGranted`) genuinely reads better than injecting the dependency yourself. Anything a shortcut doesn't cover, inject explicitly.

## Input and Access

- Validate with Symfony Validator constraints on a typed DTO or a Form type — not with ad-hoc `if` checks inside the action.
- Gate access through voters via `denyAccessUnlessGranted(...)` or `#[IsGranted]`; never read roles by hand in a controller. See `security.md`.
