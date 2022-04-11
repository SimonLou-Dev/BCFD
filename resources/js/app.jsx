/**
* First we will load all of this project's JavaScript dependencies which
* includes React and other helpers. It's a great starting point while
* building robust, powerful web applications using React + Laravel.
*/

/**
 * Next, we will create a fresh React component instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

import "../sass/app.scss";
import React from 'react';
import ReactDOM from 'react-dom';
import * as Sentry from "@sentry/react";
import NotificationsProvider from "./components/context/NotificationProvider";
import {BrowserRouter, Route, Router, Switch} from "react-router-dom";
import Login from "./components/AuthComponent/Login";
import Register from "./components/AuthComponent/Register";
import Maintenance from "./components/Maintenance";
import GetInfos from "./components/AuthComponent/GetInfos";
import Cantaccess from "./components/AuthComponent/Cantaccess";
import Layout from "./components/App/Layout";
import ServiceNav from "./components/AuthComponent/ServiceNav";
import { ReportingObserver as ReportingObserverIntegration,
    ExtraErrorData as ExtraErrorDataIntegration,
    CaptureConsole as CaptureConsoleIntegration} from "@sentry/integrations";

import {BrowserTracing} from "@sentry/tracing/dist/browser";
import { createBrowserHistory } from "history";
const history = createBrowserHistory();


  Sentry.init({
      dsn: "https://58dd69b50a3d4472b479985e877e1b1c@sentry.simon-lou.com/3",
      tunnel: "/tunnel",
      environment: import.meta.env,
      integrations: [
          new BrowserTracing({
              routingInstrumentation: Sentry.reactRouterV5Instrumentation(history),
          }),new ExtraErrorDataIntegration({ depth: 3,}),
          new CaptureConsoleIntegration({levels: ['warn', 'error'],}),
          new ReportingObserverIntegration(
              {types: ["crash", "deprecation","intervention"]}
          )
     ],
      tracesSampleRate: 1.0,
  });

class App extends React.Component{
    constructor(props) {
        super(props);
        this.state = {
            space: 0,
        }

    }
    render() {
        return(
            <Sentry.ErrorBoundary showDialog={false}>

                <NotificationsProvider>
                    <Router history={history}>
                        <Switch>
                            <Route path='/login' component={Login}/>
                            <Route path='/register' component={Register}/>
                            <Route path='/cantaccess' component={Cantaccess}/>
                            <Route path='/informations' component={GetInfos}/>
                            <Route path='/maintenance' component={Maintenance}/>

                            <Layout/>
                        </Switch>
                    </Router>
                </NotificationsProvider>
            </Sentry.ErrorBoundary>
        )
    }


}
export default Sentry.withProfiler(App);

if (document.getElementById('app')) {
    ReactDOM.render(<App/>, document.getElementById('app'));
}
