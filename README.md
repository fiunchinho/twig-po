twig-po
=======

Extract translation keys from twig templates and move them to PO

Instructions
------------

Fork and install (you need composer for that!):

    git clone git@github.com:your-user/twig-po.git
    cd twig-po
    composer install

Then, go to folder and execute:

    ./console find /path/to/twig/templates  /path/to/messages.po --dry-run

Once you see that nothing wrong is going to happen then

    ./console find /path/to/twig/templates  /path/to/messages.po

If you want to change the tag from "trans" (that is: "{% trans %}translation{% endtrans %}") to another, add -t=yourtag .

Once you have the PO translated, convert to .mo with your editor or command line:

    msgfmt -cv -o messages.mo messages.po

(Note: you need to have gettext installed for this command)
