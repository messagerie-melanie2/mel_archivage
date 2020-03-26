<?php

class mel_archivage extends rcube_plugin
{
    /**
     * Task courante pour le plugin
     *
     * @var string
     */
    public $task = 'mail|settings';

    // RFC4155: mbox date format
    const MBOX_DATE_FORMAT = 'D M d H:i:s Y';

    function init()
    {
        // check requirements first
        if (!class_exists('ZipArchive', false)) {
            rcmail::raise_error(array(
                'code'    => 520,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'message' => "php_zip extension is required for the zipdownload plugin"
            ), true, false);
            return;
        }

        $rcmail = rcmail::get_instance();

        $this->load_config();
        $this->charset = $rcmail->config->get('zipdownload_charset', RCUBE_CHARSET);


        $this->include_script('mel_archivage.js');
        $this->add_texts('localization', true);

        $this->register_action('plugin.mel_archivage', array($this, 'request_action'));
        $this->register_action('plugin.mel_archivage_traitement', array($this, 'traitement_archivage'));
        if ($rcmail->task == 'settings' || $rcmail->task == 'mail') {
            if ($rcmail->config->get('ismobile', false)) {
                $skin_path = 'skins/mel_larry_mobile';
            } else {
                $skin_path = $this->local_skin_path();
            }
            $this->include_stylesheet($skin_path . '/css/mel_archivage.css');
        }
    }

    public function request_action()
    {
        $rcmail = rcmail::get_instance();

        $rcmail->output->send('mel_archivage.mel_archivage');
    }

    public function traitement_archivage()
    {
        $nbJours = $_GET['nb_jours'];
        $datetime1 = new DateTime(date('Y-m-d'));

        $rcmail = rcmail::get_instance();
        $storage = $rcmail->get_storage();
        // $mbox = self::restore_bal();


        $threading = (bool) $storage->get_threading();
        $old_count = $storage->count(null, $threading ? 'THREADS' : 'ALL');

        $mbox           = $storage->get_folder();
        $msg_count      = $storage->count(null, $threading ? 'THREADS' : 'ALL');
        $exists         = $storage->count($mbox, 'EXISTS', true);
        $page_size      = $storage->get_pagesize();
        $pages          = ceil($msg_count / $page_size);
        
        $message_uid = array();
        $messageset = array();
        for ($page = 1; $page <= $pages; $page++) {
            foreach ($storage->list_messages($mbox, $page, 'date', 'ASC') as $message) {
                $datetime2 = new DateTime(date("Y-m-d", strtotime($message->date)));
                $interval = $datetime1->diff($datetime2);
                $interval = $interval->format('%a');
                if ($interval > $nbJours) {
                    $message_uid[] = $message->uid;
                    $messageset[$message->folder] = $message_uid;
                }
            }
        }

        $this->_download_messages($messageset);

        $rcmail->output->send('mel_archivage.mel_archivage');
    }

    // public function restore_bal() {

    //     $rcmail = rcmail::get_instance();

    //     $mbox = driver_mel::gi()->getUser($rcmail->plugins->get_plugin('mel')->get_user_bal(), false);
    //     if ($mbox->is_objectshare) {
    //       $mbox = $mbox->objectshare->mailbox;
    //     }
    //     $folders = [];
    //     $imap = $rcmail->get_storage();
    //     // Si c'est la boite de l'utilisateur connecté
    //     if ($mbox->uid == $rcmail->get_user_name()) {
    //       if ($imap->connect($rcmail->user->get_username('domain'), $mbox->uid, $rcmail->get_user_password(), 993, 'ssl')) {
    //         $folders = $imap->list_folders_direct();
    //       }
    //     }
    //     else {
    //       // Récupération de la configuration de la boite pour l'affichage
    //       $host = driver_mel::gi()->getRoutage($mbox);
    //       if (isset($host)) {
    //         $imap->connect($host, $id, $rcmail->get_user_password(), 993, 'ssl');
    //         $folders = $imap->list_folders_direct();
    //       }
    //     }

    //     return $folders;
    //   }
    // /**
    //  * Helper method to send the zip archive to the browser
    //  */
    private function _deliver_zipfile($tmpfname, $filename)
    {
        $browser = new rcube_browser;
        $rcmail  = rcmail::get_instance();

        $rcmail->output->nocacheing_headers();

        if ($browser->ie)
            $filename = rawurlencode($filename);
        else
            $filename = addcslashes($filename, '"');

        // send download headers
        header("Content-Type: application/octet-stream");
        if ($browser->ie) {
            header("Content-Type: application/force-download");
        }

        // don't kill the connection if download takes more than 30 sec.
        @set_time_limit(0);
        header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
        header("Content-length: " . filesize($tmpfname));
        readfile($tmpfname);
    }

    /**
     * Helper function to convert filenames to the configured charset
     */
    private function _convert_filename($str)
    {
        $str = strtr($str, array(':' => '', '/' => '-'));

        return rcube_charset::convert($str, RCUBE_CHARSET, $this->charset);
    }


    /**
     * Helper function to convert message subject into filename
     */
    private function _filename_from_subject($str)
    {
        $str = preg_replace('/[\t\n\r\0\x0B]+\s*/', ' ', $str);

        return trim($str, " ./_");
    }

    /**
     * Helper method to packs all the given messages into a zip archive
     *
     * @param array List of message UIDs to download
     */
    private function _download_messages($messageset)
    {
        $rcmail    = rcmail::get_instance();
        $imap      = $rcmail->get_storage();
        $mode      = rcube_utils::get_input_value('_mode', rcube_utils::INPUT_POST);
        $temp_dir  = $rcmail->config->get('temp_dir');
        $tmpfname  = tempnam($temp_dir, 'zipdownload');
        $tempfiles = array($tmpfname);
        $folders   = count($messageset) > 1;

        // @TODO: file size limit

        // open zip file
        $zip = new ZipArchive();
        $zip->open($tmpfname, ZIPARCHIVE::OVERWRITE);

        foreach ($messageset as $mbox => $uids) {
            $imap->set_folder($mbox);
            $path = $folders ? str_replace($imap->get_hierarchy_delimiter(), '/', $mbox) . '/' : '';

            if ($uids === '*') {
                $index = $imap->index($mbox, null, null, true);
                $uids  = $index->get();
            }

            foreach ($uids as $uid) {
                $headers = $imap->get_message_headers($uid);

                if ($mode == 'mbox') {
                    // Sender address
                    $from = rcube_mime::decode_address_list($headers->from, null, true, $headers->charset, true);
                    $from = array_shift($from);
                    $from = preg_replace('/\s/', '-', $from);

                    // Received (internal) date
                    $date = rcube_utils::anytodatetime($headers->internaldate);
                    if ($date) {
                        $date->setTimezone(new DateTimeZone('UTC'));
                        $date = $date->format(self::MBOX_DATE_FORMAT);
                    }

                    // Mbox format header (RFC4155)
                    $header = sprintf(
                        "From %s %s\r\n",
                        $from ?: 'MAILER-DAEMON',
                        $date ?: ''
                    );

                    fwrite($tmpfp, $header);

                    // Use stream filter to quote "From " in the message body
                    stream_filter_register('mbox_filter', 'zipdownload_mbox_filter');
                    $filter = stream_filter_append($tmpfp, 'mbox_filter');
                    $imap->get_raw_body($uid, $tmpfp);
                    stream_filter_remove($filter);
                    fwrite($tmpfp, "\r\n");
                } else { // maildir
                    $subject = rcube_mime::decode_header($headers->subject, $headers->charset);
                    $subject = $this->_filename_from_subject(mb_substr($subject, 0, 16));
                    $subject = $this->_convert_filename($subject);

                    $disp_name = $path . $uid . ($subject ? " $subject" : '') . '.eml';

                    $tmpfn = tempnam($temp_dir, 'zipmessage');
                    $tmpfp = fopen($tmpfn, 'w');
                    $imap->get_raw_body($uid, $tmpfp);
                    $tempfiles[] = $tmpfn;
                    fclose($tmpfp);
                    $zip->addFile($tmpfn, $disp_name);
                }
            }
        }

        $filename = $folders ? 'messages' : $imap->get_folder();

        if ($mode == 'mbox') {
            $tempfiles[] = $tmpfname . '.mbox';
            fclose($tmpfp);
            $zip->addFile($tmpfname . '.mbox', $filename . '.mbox');
        }

        $zip->close();

        $this->_deliver_zipfile($tmpfname, $filename . '.zip');

        // delete temporary files from disk
        foreach ($tempfiles as $tmpfn) {
            unlink($tmpfn);
        }

        exit;
    }
}
