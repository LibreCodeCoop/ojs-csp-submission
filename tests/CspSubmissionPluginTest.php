<?php

/**
 * @file plugins/generic/cspSubmission/tests/CspSubmissionPluginTest.php
 *
 * @class CspSubmissionPluginTest
 * @ingroup plugins_generic_cspSubmission
 *
 * @brief Testes unitários para CspSubmissionPlugin.
 */

namespace APP\plugins\generic\CspSubmission\tests;

// Carrega explicitamente porque o diretório (cspSubmission) difere do namespace
// (CspSubmission) em capitalização — o autoloader PSR-4 não resolve em Linux.
require_once dirname(__DIR__) . '/CspSubmissionPlugin.php';

use APP\plugins\generic\CspSubmission\CspSubmissionPlugin;
use PHPUnit\Framework\TestCase;

class CspSubmissionPluginTest extends TestCase
{
    // -------------------------------------------------------------------------
    // getWordCountLimit
    // -------------------------------------------------------------------------

    /**
     * @dataProvider wordCountLimitProvider
     */
    public function testGetWordCountLimit(string $section, ?array $expected): void
    {
        $this->assertSame($expected, CspSubmissionPlugin::getWordCountLimit($section));
    }

    public static function wordCountLimitProvider(): array
    {
        return [
            // Seções com limite de 6000 palavras
            'ARTIGO'        => ['ARTIGO',        ['max' => 6000, 'threshold' => 6600]],
            'DEBATE'        => ['DEBATE',         ['max' => 6000, 'threshold' => 6600]],
            'QUEST_METOD'   => ['QUEST_METOD',    ['max' => 6000, 'threshold' => 6600]],
            'ENTREVISTA'    => ['ENTREVISTA',     ['max' => 6000, 'threshold' => 6600]],
            'ESP_TEMATICO'  => ['ESP_TEMATICO',   ['max' => 6000, 'threshold' => 6600]],

            // Seções com limite de 2500 palavras
            'EDITORIAL'     => ['EDITORIAL',      ['max' => 2500, 'threshold' => 2750]],
            'COM_BREVE'     => ['COM_BREVE',       ['max' => 2500, 'threshold' => 2750]],
            'PERSPECT'      => ['PERSPECT',        ['max' => 2500, 'threshold' => 2750]],

            // Seções com limite de 8000 palavras
            'REVISAO'       => ['REVISAO',         ['max' => 8000, 'threshold' => 8800]],
            'ENSAIO'        => ['ENSAIO',          ['max' => 8000, 'threshold' => 8800]],

            // Seções com limite de 1400 palavras
            'CARTA'         => ['CARTA',           ['max' => 1400, 'threshold' => 1540]],
            'COMENTARIOS'   => ['COMENTARIOS',     ['max' => 1400, 'threshold' => 1540]],
            'RESENHA'       => ['RESENHA',         ['max' => 1400, 'threshold' => 1540]],

            // Seções com limites individuais
            'OBTUARIO'      => ['OBTUARIO',        ['max' => 1000, 'threshold' => 1050]],
            'ERRATA'        => ['ERRATA',          ['max' => 700,  'threshold' => 770]],

            // Seções sem limite definido
            'DESCONHECIDA'  => ['DESCONHECIDA',    null],
            'vazia'         => ['',                null],
        ];
    }

    public function testWordCountAboveThresholdFails(): void
    {
        $limit = CspSubmissionPlugin::getWordCountLimit('ARTIGO');
        $this->assertGreaterThan($limit['threshold'], 6601);
    }

    public function testWordCountAtThresholdPasses(): void
    {
        $limit = CspSubmissionPlugin::getWordCountLimit('ARTIGO');
        $this->assertFalse(6600 > $limit['threshold']);
    }

}
