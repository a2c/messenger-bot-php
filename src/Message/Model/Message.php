<?php 

namespace Message\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="Message")
 */
class Message 
{
    /**
     * @ORM\Id @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     * @var integer
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     * @var datetime
     */
    private $created;

    /**
     * @ORM\Column(type="string", length=140)
     *
     * @var string
     */
    private $text;

	/**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    public function getCreated()
    {
        return $this->created->format('Y-m-d H:i:s');
    }
    
    public function setCreated($created)
    {
        $this->created = $created;    
    } 

    public function getText()
    {
        return $this->text;
    }
    
    public function setText($text)
    {
        $this->text = strip_tags($text);    
    }
}