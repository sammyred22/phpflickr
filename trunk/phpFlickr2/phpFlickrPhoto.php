<?

/* Methods to implement:

photos
    * flickr.photos.addTags
    * flickr.photos.getContactsPhotos
    * flickr.photos.getContactsPublicPhotos
    * flickr.photos.getContext
    * flickr.photos.getCounts
    * flickr.photos.getExif
    * flickr.photos.getInfo
    * flickr.photos.getNotInSet
    * flickr.photos.getPerms
    * flickr.photos.getRecent
    * flickr.photos.getSizes
    * flickr.photos.getUntagged
    * flickr.photos.removeTag
    * flickr.photos.search
    * flickr.photos.setDates
    * flickr.photos.setMeta
    * flickr.photos.setPerms
    * flickr.photos.setTags

photos.licenses

    * flickr.photos.licenses.getInfo

photos.notes

    * flickr.photos.notes.add
    * flickr.photos.notes.delete
    * flickr.photos.notes.edit

photos.transform

    * flickr.photos.transform.rotate

*/


class phpFlickr_Photo extends phpFlickr_BaseObject {
    
    function phpFlickr_Photo(&$phpFlickr, $data = NULL) {
        parent::phpFlickr_BaseObject($phpFlickr, $data);
        if (is_array($data) && $data['_name'] == "photo") {
            $this->data = $data;
        } elseif (is_array($data)) {
            $this->data = $this->f->photos_getInfo($data['id'], $data['secret']);
        } elseif (!is_null($data)) {
            $this->data = $this->f->photos_getInfo($data);
        } else {
            $this->data = $this->f->photos_getInfo("0");
        }
        
        //This will get the user's nice shortcut, if it exists (and if they
        //have more than one photo in their photostream).  This is a very
        //klugey way of doing this, but I didn't see anywhere else it was
        //returned in the API.  If you have a cleaner suggestion, please
        //let me know.
        $context = $this->getContext();
        if ($context['prevphoto']['id']) {
			$this->data['owner']['shortcut'] = substr($context['prevphoto']['url'],8,strpos($context['prevphoto']['url'],"/",8)-8);
        } elseif ($context['nextphoto']['id']) {
			$this->data['owner']['shortcut'] = substr($context['nextphoto']['url'],8,strpos($context['nextphoto']['url'],"/",8)-8);
        }
    }

    function getInfo() {
        return $this->data;
    }

    function refreshInfo() {
        if(isset($this->data['exif'])) {
            $tmp['exif'] = $this->getExif(true);
        }
        if(isset($this->data['perms'])) {
            $tmp['perms'] = $this->getPerms(true);
        }
        if(isset($this->data['sizes'])) {
            $tmp['sizes'] = $this->getSizes(true);
        }
        $this->data = $this->f->photos_getInfo($this->data['id'], $this->data['secret'], true);
        $context = $this->getContext();
        if ($context['prevphoto']['id']) {
			$this->data['owner']['shortcut'] = substr($context['prevphoto']['url'],8,strpos($context['prevphoto']['url'],"/",8)-8);
        } elseif ($context['nextphoto']['id']) {
			$this->data['owner']['shortcut'] = substr($context['nextphoto']['url'],8,strpos($context['nextphoto']['url'],"/",8)-8);
		}
		$this->data = array_merge($this->data, $tmp);
        return $this->data;
    }
    
    function getURL() {
        if (empty($this->data['id']) || empty($this->data['owner']['nsid'])) {
            $this->data = $this->refreshInfo();
        }
        
        if (!empty($this->data['owner']['shortcut'])) {
			return 'http://www.flickr.com/photos/' . $this->data['owner']['shortcut'] . "/" . $this->data['id'] . "/";
		} else {
			return 'http://www.flickr.com/photos/' . $this->data['owner']['nsid'] . "/" . $this->data['id'] . "/";
		}
    }
    
    function addTags($tags) {
        $result = $this->f->photos_addTags($this->data['id'], $tags);
        $this->refreshInfo();
        return $result;
    }

    function setTags($tags = "") {
        $result = $this->f->photos_setTags($this->data['id'], $tags);
        $this->refreshInfo();
        return $result;
    }

    function deleteTag($tag) {
        $tags = $this->getTags(false);
        echo print_r($tags);
        foreach ($tags as $e) {
            if (strtolower($e) != strtolower($tag)) {
                $new_tags[] = $e;
            }
        }
        $result = $this->setTags(implode(" ", $new_tags));
        return $result;
    }
    
    function getTags($extra = false) {
        $tags = array();
        foreach ($this->data['tags']['tag'] as $tag) {
            if ($extra) {
                $tags[] = array("value" => $tag['_value'], "author" => $tag['author'], "id" => $tag['id'], "raw" => $tag['raw']);
            } else {
                $tags[] = $tag['_value'];
            }
        }
        return $tags;
    }
    
    function getContext () {
		return $this->f->photos_getContext($this->data['id']);
    }
    
    function getPrev ($useObject = true) {
		$context = $this->getContext();
		if ($context['prevphoto']['id']) {
			if ($useObject) {
				return new phpFlickr_Photo($this->f, $context['prevphoto']['id']);
			} else {
				return $context['prevphoto'];
			}
		} else {
			return false;
		}
    }

    function getNext ($useObject = true) {
		$context = $this->getContext();
		if ($context['nextphoto']['id']) {
			if ($useObject) {
				return new phpFlickr_Photo($this->f, $context['nextphoto']['id']);
			} else {
				return $context['nextphoto'];
			}
		} else {
			return false;
		}
    }
    
    function getExif ($refresh = false) {
        if (!empty($this->data['exif']) && !$refresh) {
            return $this->data['exif'];
        } else {
            $rsp = $this->f->photos_getExif($this->data['id'], $this->data['secret']);
            unset($this->data['exif']);
            foreach ($rsp['exif'] as $e) {
                if (empty($this->data['exif'][$e['label']])) {
                    $this->data['exif'][$e['label']] = (empty($e['clean']) ? $e['raw'] : $e['clean']);
                }
            }
            //$this->data['exif'] = $rsp['exif'];
            return $this->data['exif'];
        }
    }

    function getPerms ($refresh = false) {
        if (!empty($this->data['perms']) && !$refresh) {
            return $this->data['perms'];
        } else {
            $this->data['perms'] = $this->f->photos_getPerms($this->data['id']);
            return $this->data['perms'];
        }
    }

    function getSizes ($refresh = false) {
        if (!empty($this->data['sizes']) && !$refresh) {
            return $this->data['sizes'];
        } else {
            $this->data['sizes'] = $this->f->photos_getSizes($this->data['id'], $this->data['secret']);
            return $this->data['sizes'];
        }
    }
    
    function setMeta($title = NULL, $description = NULL) {
        if ($title === NULL) {
            $title = $this->data['title'];
        }
        if ($description === NULL) {
            $description = $this->data['description'];
        }
        $result = $this->f->photos_setMeta($this->data['id'], $title, $description);
        $this->refreshInfo();
        return $result;
    }
    
    function setTitle($title)
    {
        $result = $this->setMeta($title, NULL);
        return $result;
    }
    
    function setDescription($description)
    {
        $result = $this->setMeta(NULL, $description);
        return $result;
    }
}

?>
