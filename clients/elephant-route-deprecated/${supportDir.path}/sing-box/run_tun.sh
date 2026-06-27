#!/bin/bash
CMD="'$1' run -c '$2'"
osascript -e "do shell script \"$CMD\" with administrator privileges"
