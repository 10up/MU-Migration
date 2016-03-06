<?php

/*
 * This file is part of Zippy.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Zippy\Parser;

use Alchemy\Zippy\Exception\InvalidArgumentException;

class ParserFactory
{

    private static $zipDateFormat = 'Y-m-d H:i';

    /**
     * @param string $format Date format used to parse ZIP file listings
     */
    public static function setZipDateFormat($format)
    {
        self::$zipDateFormat = $format;
    }

    /**
     * Maps the corresponding parser to the selected adapter
     *
     * @param   $adapterName An adapter name
     *
     * @return ParserInterface
     *
     * @throws InvalidArgumentException In case no parser were found
     */
    public static function create($adapterName)
    {
        switch ($adapterName) {
            case 'gnu-tar':
                return new GNUTarOutputParser();
                break;
            case 'bsd-tar':
                return new BSDTarOutputParser();
                break;
            case 'zip':
                return new ZipOutputParser(self::$zipDateFormat);
                break;

            default:
                throw new InvalidArgumentException(sprintf('No parser available for %s adapter', $adapterName));
                break;
        }
    }
}
