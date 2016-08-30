<?php

namespace Cilantro\AdminBundle\Service;

use Cilantro\AdminBundle\Entity\YoutubeVideoArtist;
use Cilantro\AdminBundle\Entity\YoutubeVideoCategory;
use Cilantro\AdminBundle\Entity\YoutubeVideoTag;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;
use Google_Client;
use Google_Service_YouTube;
use Cilantro\AdminBundle\Entity\YoutubeChannel;
use Cilantro\AdminBundle\Entity\YoutubeVideo;
use Cilantro\AdminBundle\Entity\YoutubeStats;
use Symfony\Component\DependencyInjection\Container;

class YoutubeService
{
    private $em;
    private $container;
    private $googleClient;
    private $log;
    private $googleService;
    private $customTags = ['Categoria:', 'Episodio:', 'Temporada:', 'Artista:'];

    public function __construct(Doctrine $doctrine, Container $container)
    {
        $this->em = $doctrine->getManager();
        $this->container = $container;

        $this->googleClient = new Google_Client();
        $this->googleClient->setClientId($this->container->getParameter('youtube_key'));
        $this->googleClient->setClientSecret($this->container->getParameter('youtube_secret'));
        $this->googleClient->addScope('https://www.googleapis.com/auth/youtube');

        $environment = $this->container->get('kernel')->getEnvironment();
        if($environment == 'dev') {
            $callbackUrl = $this->container->get('router')->getContext()->getScheme() . '://' .
                $this->container->get('router')->getContext()->getHost() . ':8080';
        }else{
            $callbackUrl = $this->container->get('router')->getContext()->getScheme() . '://' .
                $this->container->get('router')->getContext()->getHost();
        }
        $this->googleClient->setRedirectUri($callbackUrl);

        $snRepository = $this->em->getRepository('CilantroAdminBundle:SocialNetworkService');
        $googleService = $snRepository->findOneBy(['name'=>'Google']);
        $this->googleClient->setAccessToken($googleService->getAccessToken());

        $this->googleService = new Google_Service_YouTube($this->googleClient);
    }

    public function channel()
    {
        $channels = $this->googleService->channels->listChannels('snippet', array('id'=>'UCuFAb_NwdzeyAYTp38jBvRw'));

        $youtubeChannelRespository = $this->em->getRepository('CilantroAdminBundle:YoutubeChannel');

        if(!empty($channels)) {
            foreach ($channels->getItems() as $item) {
                $youtubeChannelTemp = $youtubeChannelRespository->findBy(array('channelId' => $item->id));

                if (empty($youtubeChannelTemp)) {
                    $youtubeChannel = new YoutubeChannel();
                    $youtubeChannel->setChannelId($item->id);
                    $youtubeChannel->setTitle($item->getSnippet()->title);
                    $youtubeChannel->setDescription($item->getSnippet()->description);
                    $youtubeChannel->setPublishedAt(new \DateTime($item->getSnippet()->publishedAt));
                    $youtubeChannel->setActive(true);
                    $youtubeChannel->setAdult(0);

                    try {
                        $this->em->persist($youtubeChannel);
                        $this->em->flush();
                    } catch (\Exception $e) {
                        echo $e->getMessage();
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function video()
    {
        $youtubeChannelRespository = $this->em->getRepository('CilantroAdminBundle:YoutubeChannel');
        $youtubeVideoRespository = $this->em->getRepository('CilantroAdminBundle:YoutubeVideo');

        $youtubeChannels = $youtubeChannelRespository->findBy(Array('active'=>true));

        if(!empty($youtubeChannels)) {
            $videosAgregados = 0;

            foreach ($youtubeChannels as $youtubeChannel) {
                $lastVideo = $youtubeVideoRespository->findBy(Array('youtubeChannel'=>$youtubeChannel), Array('publishedAt' => 'DESC'), 1);
                if (empty($lastVideo)) {
                    $videos = $this->googleService->search->listSearch('snippet,id', array('order' => 'date', 'maxResults' => '50', 'channelId' => $youtubeChannel->getChannelId()));
                } else {
                    $lastVideoDate = date("Y-m-d\T00:00:00\Z", $lastVideo[0]->getPublishedAt()->getTimestamp());
                    $videos = $this->googleService->search->listSearch('snippet,id', array('order' => 'date', 'maxResults' => '50', 'channelId' => $youtubeChannel->getChannelId(), 'publishedAfter' => $lastVideoDate));
                }

                do {
                    foreach ($videos->getItems() as $item) {
                        if (!isset($item->getId()->videoId))
                            continue;

                        $youtubeVideoTemp = $youtubeVideoRespository->findBy(array('videoId' => $item->getId()->videoId));

                        if (empty($youtubeVideoTemp)) {
                            $youtubeVideo = new YoutubeVideo();
                            $youtubeVideo->setVideoId($item->getId()->videoId);
                            $youtubeVideo->setTitle($item->getSnippet()->title);
                            $youtubeVideo->setDescription($item->getSnippet()->description);
                            $youtubeVideo->setThumbnail($item->getSnippet()->getThumbnails()->high->url);
                            $youtubeVideo->setPublishedAt(new \DateTime($item->getSnippet()->publishedAt));
                            $youtubeVideo->setYoutubeChannel($youtubeChannel);
                            $youtubeVideo->setActive(true);
                            $youtubeVideo->setFront(false);

                            $videosToProcess = [$youtubeVideo];
                            $this->videoTags($videosToProcess);

                            try {
                                $this->em->persist($youtubeVideo);
                                $this->em->flush();
                                $videosAgregados++;
                            } catch (\Exception $e) {
                                echo $e->getMessage();
                                return false;
                            }
                        }
                    }
                    $nextPageToken = $videos->nextPageToken;
                    if (!empty($nextPageToken)) {
                        $videos = $this->googleService->search->listSearch('snippet,id', array('order' => 'date', 'pageToken' => $nextPageToken, 'maxResults' => '50', 'channelId' => $youtubeChannel->getChannelId()));
                    }
                } while (!empty($nextPageToken));
            }
        }

        return true;
    }

    public function videoTags(&$videosToProcess=[])
    {
        $youtubeVideosRepository = $this->em->getRepository('CilantroAdminBundle:YoutubeVideo');
        if(empty($videosToProcess)) {
            $videos = $youtubeVideosRepository->findAll();
        }else{
            $videos = $videosToProcess;
        }

        if(!empty($videos)) {
            $videoTagRepository = $this->em->getRepository('CilantroAdminBundle:YoutubeVideoTag');
            $videoArtistRepository = $this->em->getRepository('CilantroAdminBundle:YoutubeVideoArtist');
            $videoCategoryRepository = $this->em->getRepository('CilantroAdminBundle:YoutubeVideoCategory');

            foreach ($videos as $video) {
                $listResponse = $this->googleService->videos->listVideos("snippet", array('id' => $video->getVideoId()));
                $youtubeVideoTemp = $listResponse[0];
                $videoSnippet = $youtubeVideoTemp['snippet'];
                $tags = $videoSnippet['tags'];

                $video->flushTags();

                if (!empty($tags)) {
                    foreach ($tags as $tag) {
                        $tagTemp = explode(' ', $tag);
                        if(in_array($tagTemp[0], $this->customTags)){
                            switch($tagTemp[0]){
                                case "Categoria:":
                                    $videoCategory = $videoCategoryRepository->findOneBy(['name'=>$tagTemp[1]]);
                                    if(empty($videoCategory)){
                                        $videoCategory = new YoutubeVideoCategory();
                                        $tagTempName = implode(" ", array_slice($tagTemp, 1));
                                        $videoCategory->setName($tagTempName);
                                    }
                                    $video->setCategory($videoCategory);
                                    $this->em->persist($videoCategory);
                                    break;

                                case "Episodio:":
                                    $video->setEpisode($tagTemp[1]);
                                    break;

                                case "Temporada:":
                                    $video->setSeason($tagTemp[1]);
                                    break;

                                case "Artista:":
                                    $videoArtist = $videoArtistRepository->findOneBy(['name'=>$tagTemp[1]]);
                                    if(empty($videoArtist)){
                                        $videoArtist = new YoutubeVideoArtist();
                                        $tagTempName = implode(" ", array_slice($tagTemp, 1));
                                        $videoArtist->setName($tagTempName);
                                    }
                                    $video->setArtist($videoArtist);
                                    $this->em->persist($videoArtist);
                                    break;
                            }
                        }else {
                            $videoTag = $videoTagRepository->findOneBy(['name' => $tag]);
                            if (empty($videoTag)) {
                                $videoTag = new YoutubeVideoTag();
                                $videoTag->setName($tag);
                                $this->em->persist($videoTag);
                            }

                            $video->addTag($videoTag);
                        }
                    }
                }

                if(empty($videosToProcess))
                    $this->em->flush();
            }
        }
    }

    public function stats($count="")
    {
        $youtubeVideoRespository = $this->em->getRepository('CilantroAdminBundle:YoutubeVideo');
        $youtubeStatsRespository = $this->em->getRepository('CilantroAdminBundle:YoutubeStats');

        if($count == 0) {
            $youtubeVideos = $youtubeVideoRespository->findBy(Array('active' => true), Array('publishedAt'=>'DESC'));
            $logMessage = 'Youtube: All videos stats updated.';
        }else if($count != "") {
            $youtubeVideos = $youtubeVideoRespository->findBy(Array('active' => true), Array('publishedAt'=>'DESC'), $count);
            $logMessage = 'Youtube: Last '.$count.' videos stats updated.';
        }else{
            $youtubeVideos = $youtubeVideoRespository->findBy(Array('active' => true), Array('publishedAt'=>'DESC'), 30);
            $logMessage = 'Youtube: Last 30 videos stats updated.';
        }

        if(!empty($youtubeVideos)) {
            foreach ($youtubeVideos as $youtubeVideo) {
                $videos = $this->googleService->videos->listVideos('statistics', array('id' => $youtubeVideo->getVideoId()));

                foreach ($videos->getItems() as $item) {
                    $youtubeStats = $youtubeStatsRespository->findOneBy(array('youtubeVideo' => $youtubeVideo->getId()));

                    if (empty($youtubeStats)) {
                        $youtubeStats = new YoutubeStats();
                        $youtubeStats->setYoutubeVideo($youtubeVideo);
                        $youtubeStats->setCommentCount($item->getStatistics()->commentCount);
                        $youtubeStats->setDislikeCount($item->getStatistics()->dislikeCount);
                        $youtubeStats->setFavoriteCount($item->getStatistics()->favoriteCount);
                        $youtubeStats->setLikeCount($item->getStatistics()->likeCount);
                        $youtubeStats->setViewCount($item->getStatistics()->viewCount);
                        $youtubeStats->setUpdatedAt(new \DateTime());
                    } else {
                        $youtubeStats->setCommentCount($item->getStatistics()->commentCount);
                        $youtubeStats->setDislikeCount($item->getStatistics()->dislikeCount);
                        $youtubeStats->setFavoriteCount($item->getStatistics()->favoriteCount);
                        $youtubeStats->setLikeCount($item->getStatistics()->likeCount);
                        $youtubeStats->setViewCount($item->getStatistics()->viewCount);
                        $youtubeStats->setUpdatedAt(new \DateTime());
                    }

                    try {
                        $this->em->persist($youtubeStats);
                        $this->em->flush();
                    } catch (\Exception $e) {
                        echo $e->getMessage();
                        return false;
                    }
                }
            }
        }

        return true;
    }
}