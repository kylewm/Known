namespace Idno\Common {

    class Request extends \Symfony\Component\HttpFoundation\Request {

        function isXhr()
        {
            return $this->headers->get('X-Requested-With') === 'XMLHttpRequest'
        }

    }

}
