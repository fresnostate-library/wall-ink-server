CXXFLAGS += -static -O1 -std=c++11 -DIMAGE_KEY=\"$(imageKey)\" -DTIME_ZONE=$(timeZone)
LIBSRC = BitBuffer QrCode QrSegment processImage sha1 layouts Adafruit_GFX
objects = image.o rawToWink.o processImage.o BitBuffer.o QrCode.o QrSegment.o sha1.o layouts.o Adafruit_GFX.o
VPATH = cplusplussource/qr_code_generator:cplusplussource/web:cplusplussource/Adafruit-GFX-Library:cplusplussource
CXX=g++
CC=gcc
CFLAGS += -static
SHELL := /bin/bash

#This makefile has recursive test and deploy functions.
#This is to make up for the limitations of make, which can't source bash scripts
$(info MAKELEVEL=$(MAKELEVEL))

ifeq ($(MAKELEVEL), 0)
deploy:
	bash -c "source ./web/config/settings.cfg; \
		for var in \$$(compgen -v); do export \$$var; done; \
		$(MAKE) $@"
else
deploy: genimg genconfig rawToWink
	git submodule init
	git submodule update
	source ./web/config/settings.cfg
	mkdir -p $(buildTimeWebDirectory)/log
	mkdir -p $(buildTimeWebDirectory)/image_data
	mkdir -p $(buildTimeWebDirectory)/voltage_monitor/data
	rsync -r web/. $(buildTimeWebDirectory)
	chmod -R -f g+rw $(buildTimeWebDirectory)
endif

genconfig :
	echo "#!/bin/bash" > web/config/database.sh
	echo "#Do not edit this file! It is autogenerated by the makefile, and will be overwritten!" >> web/config/database.sh
	echo "#Instead, edit settings.cfg" >> web/config/database.sh
	cat web/config/settings.cfg | sed -n '/^#/!p' >> web/config/database.sh
	bash ./web/config/settings_check.sh
	cat web/config/settings.cfg | sed -n '/^#/!p' > web/config/temp
	sed -i 's/^\(.*\)$$/\$$config->\1;/' web/config/temp
	echo "<?php" > web/config/dbconfig.php
	echo "#Do not edit this file! It is autogenerated by the makefile, and will be overwritten!" >> web/config/dbconfig.php
	echo "#Instead, edit settings.cfg" >> web/config/dbconfig.php
	echo '$$config = new StdClass();' >> web/config/dbconfig.php
	cat web/config/temp >> web/config/dbconfig.php
	echo "?>" >> web/config/dbconfig.php
	rm web/config/temp

genimg : image.o processImage.o BitBuffer.o QrCode.o QrSegment.o layouts.o Adafruit_GFX.o
	$(CXX) image.o $(LIBSRC:=.o) $(CXXFLAGS) -o web/genimg

rawToWink : rawToWink.o processImage.o
	$(CXX) rawToWink.o processImage.o sha1.o $(CXXFLAGS) -o web/rawToWink

image.o : image.h
rawToWink.o : rawToWink.cpp processImage.cpp processImage.h
processImage.o : processImage.h sha1.o web/config/settings.cfg
BitBuffer.o : BitBuffer.hpp
QrCode.o : QrCode.hpp
QrSegment.o : QrSegment.hpp
sha1.o : sha1.h
layouts.o : layouts.h
Adafruit_GFX.o : Adafruit_GFX.h

clean : 
	rm -v $(objects) web/genimg web/rawToWink
