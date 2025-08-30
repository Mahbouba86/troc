<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:uml:generate',
    description: 'Génère un diagramme UML (PlantUML + Mermaid) depuis les métadonnées Doctrine'
)]
class GenerateUmlCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'plantuml|mermaid|both', 'both')
            ->addOption('out', null, InputOption::VALUE_OPTIONAL, 'Chemin fichier de sortie (sans ext si both)', 'var/uml/diagram')
            ->addOption('namespace-prefix', null, InputOption::VALUE_OPTIONAL, 'Préfixe à retirer des FQCN', 'App\\Entity')
            ->addOption('with-fields', null, InputOption::VALUE_NONE, 'Inclure les champs/colonnes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format    = strtolower((string) $input->getOption('format'));
        $outBase   = (string) $input->getOption('out');
        $nsPrefix  = trim((string) $input->getOption('namespace-prefix'), '\\');
        $withFields = (bool) $input->getOption('with-fields');

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        if (!$metadata) {
            $output->writeln('<error>Aucune entité détectée.</error>');
            return Command::FAILURE;
        }

        $info = $this->normalize($metadata, $nsPrefix, $withFields);
        @mkdir(\dirname($outBase), 0777, true);

        if ($format === 'plantuml' || $format === 'both') {
            file_put_contents($outBase . '.puml', $this->renderPlantUml($info));
            $output->writeln('<info>✔ PlantUML :</info> ' . $outBase . '.puml');
        }
        if ($format === 'mermaid' || $format === 'both') {
            file_put_contents($outBase . '.mmd', $this->renderMermaid($info));
            $output->writeln('<info>✔ Mermaid :</info> ' . $outBase . '.mmd');
        }

        return Command::SUCCESS;
    }

    /** @param array<int, ClassMetadata> $metadata */
    private function normalize(array $metadata, string $nsPrefix, bool $withFields): array
    {
        $classes = [];
        $assocs  = [];

        foreach ($metadata as $m) {
            $fqcn  = $m->getName();
            $short = str_starts_with($fqcn, $nsPrefix) ? ltrim(substr($fqcn, \strlen($nsPrefix)), '\\') : $fqcn;
            $short = ltrim($short, '\\');
            $fields = [];

            if ($withFields) {
                foreach ($m->fieldMappings as $name => $map) {
                    $type = $map['type'] ?? 'mixed';
                    $nullable = !empty($map['nullable']) ? '?' : '';
                    $fields[] = [
                        'name' => $name,
                        'type' => $nullable . $type,
                        'id'   => !empty($map['id']),
                    ];
                }
            }

            $classes[$fqcn] = [
                'name'   => $short ?: $fqcn,
                'fields' => $fields,
            ];

            foreach ($m->associationMappings as $assoc) {
                $from = $classes[$fqcn]['name'];
                $toFqcn = $assoc['targetEntity'] ?? '';
                $to = str_starts_with($toFqcn, $nsPrefix) ? ltrim(substr($toFqcn, \strlen($nsPrefix)), '\\') : $toFqcn;
                $to = ltrim($to, '\\') ?: $toFqcn;

                $type = $assoc['type'] ?? 0;
                $cardFrom = '';
                $cardTo = '';

                switch ($type) {
                    case ClassMetadata::ONE_TO_ONE:
                        $cardFrom = '1'; $cardTo = '1';
                        break;
                    case ClassMetadata::MANY_TO_ONE:
                        $cardFrom = 'N'; $cardTo = '1';
                        break;
                    case ClassMetadata::ONE_TO_MANY:
                        $cardFrom = '1'; $cardTo = 'N';
                        break;
                    case ClassMetadata::MANY_TO_MANY:
                        $cardFrom = 'N'; $cardTo = 'N';
                        break;
                }

                $assocs[] = [
                    'from' => $from,
                    'to'   => $to,
                    'cardFrom' => $cardFrom,
                    'cardTo'   => $cardTo,
                    'field'    => $assoc['fieldName'] ?? null,
                ];
            }
        }

        return ['classes' => array_values($classes), 'associations' => $assocs];
    }

    private function renderPlantUml(array $info): string
    {
        $lines = [
            '@startuml',
            'hide empty members',
            'skinparam classBackgroundColor #FFFFFF',
            'skinparam classBorderColor #666666',
            'skinparam shadowing false',
            ''
        ];

        foreach ($info['classes'] as $c) {
            $lines[] = 'class ' . $this->quote($c['name']) . ' {';
            foreach ($c['fields'] as $f) {
                $prefix = !empty($f['id']) ? '+' : ' ';
                $lines[] = sprintf('  %s%s: %s', $prefix, $f['name'], $f['type']);
            }
            $lines[] = '}';
            $lines[] = '';
        }

        foreach ($info['associations'] as $a) {
            $label = $a['field'] ? ' : ' . $a['field'] : '';
            $lines[] = sprintf('"%s" -- "%s" %s : %s%s',
                $a['cardFrom'], $a['cardTo'], $this->quote($a['from']), $this->quote($a['to']), $label
            );
        }

        $lines[] = '@enduml';
        return implode("\n", $lines);
    }

    private function renderMermaid(array $info): string
    {
        $lines = ['classDiagram'];

        foreach ($info['classes'] as $c) {
            $lines[] = 'class ' . $this->safeId($c['name']) . ' {';
            foreach ($c['fields'] as $f) {
                $prefix = !empty($f['id']) ? '+ ' : '';
                $lines[] = sprintf('  %s%s : %s', $prefix, $f['name'], $f['type']);
            }
            $lines[] = '}';
            $lines[] = '';
        }

        foreach ($info['associations'] as $a) {
            $from = $this->safeId($a['from']);
            $to   = $this->safeId($a['to']);
            $cf = $a['cardFrom'] === 'N' ? '"*"' : ($a['cardFrom'] === '1' ? '"1"' : '');
            $ct = $a['cardTo']   === 'N' ? '"*"' : ($a['cardTo']   === '1' ? '"1"' : '');
            $field = $a['field'] ? ' : ' . $a['field'] : '';
            $lines[] = sprintf('%s %s -- %s %s%s', $from, $cf, $ct, $to, $field);
        }

        return implode("\n", $lines);
    }

    private function quote(string $name): string
    {
        return preg_match('~^[A-Za-z_][A-Za-z0-9_]*$~', $name) ? $name : '"' . $name . '"';
    }

    private function safeId(string $name): string
    {
        // Transforme en identifiant simple : lettres/chiffres/underscore
        $id = preg_replace('~[^A-Za-z0-9_]+~', '_', $name) ?? $name;
        if (!preg_match('~^[A-Za-z_]~', $id)) {
            $id = '_' . $id;
        }
        return $id;
    }
}
