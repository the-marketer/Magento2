# <img style="height:35px;vertical-align: middle;" src="https://github.com/eaxlex/OpenCart-System/blob/latest/library/mktr/logo.png" alt="TheMarketer"> TheMarketer - Magento 2

## Compatible with:
    - Magento 2

## Install TheMarketer
Copy mktr.zip to store_root_folder
```shell
cd path_to_the_store_root_folder
unzip -o mktr.zip -d mktr
sh mktr/mktr.sh install # or 
sh mktr/mktr.sh install php7.4 # if you have multiple php versions you can add it 
```

## unInstall TheMarketer
```shell
cd path_to_the_store_root_folder
sh mktr/mktr.sh uninstall # or 
sh mktr/mktr.sh uninstall php7.4 # if you have multiple php versions you can add it 
```

+ Param "store"
+ remove vsprintf

+ Use as
+ /mktr/api/Feed?key={REST_KEY}&store={STORE_ID|STORE_CODE}

+ Magento 2 Default
+ /mktr/api/Feed?key={REST_KEY}&___store={STORE_CODE}