<?php
namespace TranslationsFinder;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Find extends Command
{
    const TAG_REGEX      = '/{% ?trans ?%}(.*)(?:{% ? plural (.*)?%}(.*))?{% ?endtrans ?%}/muU';
    const MODIFIER_REGEX = '/([a-zA-Z_0-9]+)|trans/muU';
    const MSGID_REGEX    = '/msgid "(.*)"/mu';

    protected $last_filename;

    // searcher stats
    protected $n_found_tags = 0;
    protected $n_read_files = 0;

    // program options
    protected $dry_run = false;
    protected $verbose = false;
    protected $output_tags = false;

    protected function configure()
    {
        $this->setName( 'find' )->setDescription( 'Find {%trans%} tags in a directory' )->addArgument(
            'path',
            InputArgument::REQUIRED,
            'Please include the path where you want me to find tags'
        )->addArgument(
            'po-file',
            InputArgument::REQUIRED,
            'PO file for check and write to'
        )->addOption(
            'dry-run',
            'd',
            InputOption::VALUE_NONE,
            'Do not write the new tags in the PO file'
        )->addOption(
            'verbose',
            'v',
            InputOption::VALUE_NONE,
            'Output information of every step'
        )->addOption(
            'output-tags',
            'o',
            InputOption::VALUE_NONE,
            'Output the tags as they will appear in the final PO file'
        );
    }

    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $path              = rtrim( $input->getArgument('path'), DIRECTORY_SEPARATOR );
        $po_filename       = $this->filterPoFilename( $input->getArgument( 'po-file' ) );

        $this->dry_run     = $input->getOption( 'dry-run' );
        $this->verbose     = $input->getOption( 'verbose' );
        $this->output_tags = $input->getOption( 'output-tags' );

        $existing_tags = $this->getMsgIdsFromFile( $po_filename );

        if ($this->verbose) {
            $output->writeln(
                "<fg=green>Found " . count( $existing_tags ) . " msgids in $po_filename.</fg=green>"
            );
        }
        if ($this->verbose) $output->writeln( "<fg=green>Searching for translations in $path recursively</fg=green>" );

        $tags = array();
        $this->searchDirectory( $path, $tags, $existing_tags );
        $n_tags       = count( $tags );
        $matched_tags = $this->n_found_tags - $n_tags;

        if ($this->verbose) $output->writeln(
            "<fg=green>Finished search! Found $this->n_found_tags tags in $this->n_read_files files</fg=green>"
        );
        if ($this->verbose && $matched_tags) $output->writeln(
            "<fg=magenta>" . $matched_tags . " tags were already in the PO file</fg=magenta>"
        );

        if ($n_tags) {

            $output_tags = $this->outputTags( $tags );

            if ($this->output_tags) {
                if ($this->verbose) $output->writeln( "<fg=green>Outputing " . $n_tags . " tags</fg=green>" );
                echo $output_tags;
            }

            if ($this->dry_run) {
                if ($this->verbose) $output->writeln( "<fg=yellow>Dry-run: PO file will not be touched</fg=yellow>" );
            } else {
                file_put_contents( $po_filename, file_get_contents( $po_filename ) . $output_tags );
                if ($this->verbose) $output->writeln( "<fg=magenta>PO FILE UPDATED!</fg=magenta>" );
            }

            // TO-DO: hacer lo de arriba bien!
        }
    }

    protected function filterPoFilename( $po_filename )
    {

        if (!is_file(
            $po_filename
        )
        ) throw new \InvalidArgumentException( "ERROR: $po_filename PO FILE does not exist" );
        return $po_filename;
    }

    /**
     * Reads all translations in the PO file and returns an array with msgid
     */
    protected function getMsgIdsFromFile( $filename )
    {

        return $this->pregMatchAllFile( $filename, self::MSGID_REGEX );
    }

    // TO-DO: accept several regex
    // TO-DO: option for returning an array_unique
    protected function pregMatchAllFile( $filename, $regex )
    {

        $res = preg_match_all( $regex, file_get_contents( $filename ), $matches );
        return ( isset( $matches[1] ) ) ? $matches[1] : array();
    }

    protected function outputTags( $tags )
    {

        $output = "";
        foreach ( $tags as $tag => $filenames ) {
            $output .= $this->outputTag( $tag, $filenames );
        }
        return $output;
    }

    protected function outputTag( $tag, $filenames = array() )
    {

        $output = '';
        foreach ( $filenames as $filename ) {
            $output .= "\n#: $filename";
        }
        $output .= <<<EOT

msgid "$tag"
msgstr ""

EOT;
        return $output;
    }

    // TO-DO: Allow plurals!
    protected function addTag( &$tags, $tag, $filename )
    {

        if (!array_key_exists( $tag, $tags )) {
            $tags[$tag] = array( $filename );
            $this->n_found_tags++;
        } else {
            $tags[$tag][] = $filename;
        }

    }

    protected function parseTag( $tag )
    {

        return preg_replace( array( '/{{ (.*) }}/muU', '/{{(.*)}}/muU' ), '%\1%', $tag );
    }

    protected function parseFile( $filename, &$tags, $existing_tags )
    {

        $this->n_read_files++;
        $matches = array_unique( $this->pregMatchAllFile( $filename, self::TAG_REGEX ) );

        foreach ( $matches as $tag ) {
            if (in_array( $tag, $existing_tags )) continue;
            $tag = $this->parseTag( $tag );
            $this->addTag( $tags, $tag, $filename );
        }
    }

    protected function searchDirectory( $path, &$tags, $existing_tags )
    {
        if( is_file( $path ) ) {
            return $this->parseFile( $path, $tags, $existing_tags );
        }

        $directory  = new \DirectoryIterator( $path );
        foreach( $directory as $fileinfo ){
            if ( !$fileinfo->isDot() ) {
                $this->searchDirectory( $path . DIRECTORY_SEPARATOR . $fileinfo->getFilename(), $tags, $existing_tags );
            }
        }
    }
}
