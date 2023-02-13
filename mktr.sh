#!/bin/sh

if [ -z "$2" ]; then
  usePhp="php"
else
  usePhp=$2
fi

start ()
{
    ${usePhp} bin/magento cache:clean
    ${usePhp} bin/magento cache:flush
    ${usePhp} bin/magento setup:upgrade
}

install ()
{
    echo "Creating Directory for Mktr"
    mkdir -p app/code/Mktr
    echo "Copy Files Mktr"
    cp -rf mktr/app/code/Mktr/Tracker app/code/Mktr
    cp -rf mktr/app/code/Mktr/Google app/code/Mktr

    read -p "Almost Done, Press enter to continue " responce

    ${usePhp} bin/magento module:enable --clear-static-content Mktr_Tracker Mktr_Google
    ${usePhp} bin/magento setup:upgrade
    ${usePhp} bin/magento setup:di:compile
    ${usePhp} bin/magento cache:flush
    ${usePhp} bin/magento setup:static-content:deploy
    ${usePhp} bin/magento cache:clean
}

uninstall ()
{
    read -r -p "Are you sure? [Y/n]" response

    response=$(echo $response | tr '[:upper:]' '[:lower:]')

    if [ "${response}" = "y" ]; then
        ${usePhp} bin/magento module:disable --clear-static-content Mktr_Tracker Mktr_Google
        ${usePhp} bin/magento module:uninstall Mktr_Tracker Mktr_Google
        ${usePhp} bin/magento setup:upgrade
        ${usePhp} bin/magento setup:di:compile
        ${usePhp} bin/magento cache:flush
        ${usePhp} bin/magento setup:static-content:deploy

        rm -rf app/code/Mktr/Tracker
    fi
}

if [ -z "$1" ]; then
    start
else
    $1
fi



