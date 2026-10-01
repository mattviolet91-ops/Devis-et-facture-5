<?php

namespace Tests;

use App\Support\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Par défaut, les tests partent d'une application déjà configurée.
     * Les tests du menu de configuration mettent cette valeur à false.
     */
    protected bool $configurationTerminee = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Aucun appel réel à Internet pendant les tests.
        Http::preventStrayRequests();

        if ($this->configurationTerminee && in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            app(Reglages::class)->set('setup.completed_at', now()->toIso8601String());
        }
    }
}
