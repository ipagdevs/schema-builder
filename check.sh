#!/bin/bash
set -e

offer_run() {
    read -p "For more output, run \"$1\". Run it now (Y/n)? " run

    case ${run:0:1} in
        n|N )
            exit 1
        ;;
        * )
            eval "$1"
        ;;
    esac

    exit 1
}

if (./vendor/bin/phpstan analyse --memory-limit=1G > /dev/null 2>/dev/null); then
    echo '✅ PHPStan OK'
else
    echo '❌ PHPStan FAILED'
    offer_run "./vendor/bin/phpstan analyse --memory-limit=1G"
fi

if (./vendor/bin/phpunit --colors=always > /dev/null 2>/dev/null); then
    echo '✅ PHPUnit OK'
else
    echo '❌ PHPUnit FAILED'
    offer_run "./vendor/bin/phpunit --colors=always"
fi

echo '=============================='
echo '✅ All checks passed (local CI)'
