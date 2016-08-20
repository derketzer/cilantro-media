<?php

namespace Cilantro\AdminBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * FacebookVideo
 *
 * @ORM\Table(name="facebook_video")
 * @ORM\Entity(repositoryClass="Cilantro\AdminBundle\Repository\FacebookVideoRepository")
 */
class FacebookVideo
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="facebookId", type="string", length=255)
     */
    private $facebookId;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=255)
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text")
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(name="embed_html", type="text")
     */
    private $embedHtml;

    /**
     * @var string
     *
     * @ORM\Column(name="picture", type="string", length=255)
     */
    private $picture;

    /**
     * @var bool
     *
     * @ORM\Column(name="published", type="boolean")
     */
    private $published;

    /**
     * @ORM\ManyToOne(targetEntity="FacebookVideoList", inversedBy="videos")
     * @ORM\JoinColumn(name="facebook_video_list_id", referencedColumnName="id")
     **/
    private $facebookVideoList;


    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set facebookId
     *
     * @param string $facebookId
     *
     * @return FacebookVideo
     */
    public function setFacebookId($facebookId)
    {
        $this->facebookId = $facebookId;

        return $this;
    }

    /**
     * Get facebookId
     *
     * @return string
     */
    public function getFacebookId()
    {
        return $this->facebookId;
    }

    /**
     * Set title
     *
     * @param string $title
     *
     * @return FacebookVideo
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return FacebookVideo
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set picture
     *
     * @param string $picture
     *
     * @return FacebookVideo
     */
    public function setPicture($picture)
    {
        $this->picture = $picture;

        return $this;
    }

    /**
     * Get picture
     *
     * @return string
     */
    public function getPicture()
    {
        return $this->picture;
    }

    /**
     * Set published
     *
     * @param boolean $published
     *
     * @return FacebookVideo
     */
    public function setPublished($published)
    {
        $this->published = $published;

        return $this;
    }

    /**
     * Get published
     *
     * @return bool
     */
    public function getPublished()
    {
        return $this->published;
    }

    /**
     * @return mixed
     */
    public function getFacebookVideoList()
    {
        return $this->facebookVideoList;
    }

    /**
     * @param mixed $facebookVideoList
     */
    public function setFacebookVideoList($facebookVideoList)
    {
        $this->facebookVideoList = $facebookVideoList;
    }

    /**
     * @return string
     */
    public function getEmbedHtml()
    {
        return $this->embedHtml;
    }

    /**
     * @param string $embedHtml
     */
    public function setEmbedHtml($embedHtml)
    {
        $this->embedHtml = $embedHtml;
    }
}

