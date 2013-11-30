<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

/**
 * Command that will validate your yml file syntax and output encountered errors.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class YmlLintCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('yml:lint')
            ->setDescription('Lints a file and outputs encountered errors')
            ->addArgument('filename')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lints a file and outputs to stdout
the first encountered syntax error.

<info>php %command.full_name% filename</info>

The command gets the contents of <comment>filename</comment> and validates its
syntax.

<info>php %command.full_name% dirname</info>

The command finds all yml files in <comment>dirname</comment> and validates the
syntax of each one.

<info>php %command.full_name% @AcmeDemoBundle</info>

The command finds all yml files in the <comment>AcmeMyBundle</comment> bundle
and validates the syntax of each one.

<info>cat filename | php %command.full_name%</info>

The command gets the template contents from stdin and validates its syntax.
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filename = $input->getArgument('filename');

        if (!$filename) {
            if (0 !== ftell(STDIN)) {
                throw new \RuntimeException('Please provide a filename or pipe file content to stdin.');
            }

            $content = '';
            while (!feof(STDIN)) {
                $content .= fread(STDIN, 1024);
            }

            return $this->validateFile($output, $content);
        }

        if (0 !== strpos($filename, '@') && !is_readable($filename)) {
            throw new \RuntimeException(sprintf('File or directory "%s" is not readable', $filename));
        }

        $files = array();
        if (is_file($filename)) {
            $files = array($filename);
        } elseif (is_dir($filename)) {
            $files = Finder::create()->files()->in($filename)->name('*.yml');
        } else {
            $dir = $this->getApplication()->getKernel()->locateResource($filename);
            $files = Finder::create()->files()->in($dir)->name('*.yml');
        }

        $errors = 0;
        foreach ($files as $file) {
            $errors += $this->validateFile($output, file_get_contents($file), $file);
        }

        return $errors > 0 ? 1 : 0;
    }

    protected function validateFile(OutputInterface $output, $content, $file = null)
    {
        $this->parser = new Parser();
        try {
            $this->parser->parse($content);
            $output->writeln('<info>OK</info>'.($file ? sprintf(' in %s', $file) : ''));
        } catch (ParseException $e) {
            if ($file) {
                $output->writeln(sprintf('<error>KO</error> in %s', $file));
            } else {
                $output->writeln('<error>KO</error>');
            }

            $output->writeln(sprintf('<error>>> %s</error>', $e->getMessage()));

            return 1;
        }

        return 0;
    }
}
