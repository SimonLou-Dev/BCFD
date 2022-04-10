import React, {useEffect, useState} from 'react';
import axios from "axios";
import GetInfos from "../AuthComponent/GetInfos";
import {Link, Route} from "react-router-dom";
import Maintenance from "../Maintenance";
import {useNotifications} from "../context/NotificationProvider";
import UserContext from "../context/UserContext";
const mysrcf = csrf;
import {v4} from "uuid";
import Rapport from "./Patient/Rapports/Rapport";
import DossiersPatient from "./Patient/Dossiers/DossiersPatient";
import RapportReview from "./Patient/Dossiers/RapportReview";
import Psycology from "./Patient/Dossiers/Psycology";
import TestPoudre from "./Patient/TestPoudre/TestPoudre";
import FactureList from "./Patient/Factures/FactureList";
import FireBC from "./Patient/BlackCode/FireBC";
import MedicBC from "./Patient/BlackCode/MedicBC";
import GlobalView from "./Patient/BlackCode/GlobalView";
import GestionStocks from "./logistique/GestionStocks";
import StockSettings from "./logistique/StockSettings";
import Grade from "./Personnel/grade";
import RapportHoraire from "./Personnel/RapportHoraire";
import ListPersonnel from "./Personnel/ListPersonnel";
import Demandes from "./Personnel/Demandes";
import FichePersonnel from "./Personnel/FichePersonnel";
import DiscordBots from "./Manager/DiscordBots";
import Logs from "./Manager/Logs";
import ContentManager from "./Manager/ContentManager";
import AnnoncesInfos from "./Manager/AnnoncesInfos";
import MyAccount from "./Other/account/MyAccount";
import ServiceNav from "../AuthComponent/ServiceNav";
import Dashboard from "./Other/Dashboard";
import Vols from "./logistique/Vols"
import * as Sentry from "@sentry/react";

function Layout(props) {
    const [collapsed, setCollasping] = useState(true);
    const [user, setUser] = useState(null);
    const [service, setService] = useState('');
    const [serviceDisabled, disableService] = useState(false);
    const [image, setImage] = useState('');
    const dispatch = useNotifications();

    useEffect(async ()=>{
        let userid = undefined
        updateUserImage()
        await axios({
            method: 'GET',
            url: '/data/userInfos',
        }).then((response)=>{
            userid = response.data.user.id;
            setUser(response.data.user);
            setService(response.data.user.service);
            Sentry.setUser({id: response.data.user.id, discord_id: response.data.user.discord_id, service: response.data.user.service})
        })
        const timerID = setInterval(
            () => tick(),
            5*60*1000
        );


        Pusher.logToConsole = false;

        let pusher = new Pusher('fd78f74e8faecbd2405b', {
            cluster: 'eu',
            authEndpoint : '/broadcasting/auth',
            auth: { headers: { "X-CSRF-Token": mysrcf } }
        });

        window.UserChannel = pusher.subscribe('private-User.'+env+'.'+ userid)
        window.UserChannel.bind('notify', (e)=>{
            addNotification(e)
        })

        window.UserChannel.bind('UserUpdated', (e)=>{
            updateUserImage()
            setUser(e.userInfos);
            setService(e.userInfos.service);
        })

        let GlobalChannel = pusher.subscribe('presence-GlobalChannel.'+env)
        window.GlobalChannel = GlobalChannel;

        GlobalChannel.bind('notify', (e)=>{
            addNotification(e)
        })


        return () => {
            clearInterval(timerID);
            pusher.unsubscribe('presece-GlobalChannel.'+env);
            pusher.unsubscribe('private-User.'+env+'.'+ userid);
        }

    }, [])

    const updateUserImage = async () => {
        await axios({
            method: 'GET',
            url: '/data/bg'
        }).then(r => {
            setImage(r.data.image)
        })
    }

    const addNotification = (data) => {
        if(!data.type){
            data.type = 'warning';
        }

        let payload = {};
        switch (data.type){
            case 1:
                payload= {
                    id:v4(),
                    type: 'success',
                    message: data.text
                }
                break
            case 2:
                payload= {
                    id:v4(),
                    type: 'info',
                    message: data.text
                }
                break;
            case 3:
                payload= {
                    id:v4(),
                    type: 'warning',
                    message: data.text
                }
                break;
            case 4:
                payload= {
                    id:v4(),
                    type: 'danger',
                    message: data.text
                }
                break;
            default: break;
        }
        dispatch({
            type: 'ADD_NOTIFICATION',
            payload: {
                id: payload.id,
                type: payload.type,
                message: payload.message
            }
        });
    }

    const tick =async() => {
        await axios({
            url: '/data/check/connexion',
            method: 'GET'
        }).then(response => {
            if(!response.data.session) {
                window.location.replace('/login')
            }
        }).catch( error => {
            if(error.response.status === 503){
                window.location.replace('/maintenance')
            }
            if(error.response.status === 401){
                window.location.replace('/login')
            }
            window.location.replace('/login');
        })

    }

    const serviceRequester = async (e) => {
        if(serviceDisabled) e.preventDefault()

       if(!serviceDisabled){
           disableService(true)
           await axios({
               method: 'PATCH',
               url: '/data/service/user'
           })
           const serviceID = setInterval(
               () => {disableService(false); clearInterval(serviceID)},
               10*1000
           );
       }
    }

    if(user === null) return (<div className={'layout'}>

    </div> )
    else{
        return(<div className={"layout"} style={{backgroundImage: 'url('+image+')'}}>
            <header className={"layout-header"}>
                <div className={'header-left'}>
                    <div className={"header-menu"} onClick={() => {
                        setCollasping(!collapsed)
                    }}>
                        <img src={'/assets/images/menu.png'} alt={""}/>
                        <h1>menu</h1>
                        <img src={'/assets/images/' + service + '.png'} alt={""} className={'service-name'}/>
                    </div>
                    <div className={'service-BTN'}>
                        <button className={'btn service ' + (serviceDisabled ? 'ServicebtnDisabled':'') + ' '  + (user.OnService ? 'serviceActive' : 'serviceDisabled')}
                                onClick={(e)=>serviceRequester(e)}>{user.OnService ? 'en service' : 'hors service'}</button>
                    </div>
                </div>
                <div className={"header-logout"}>
                    <a href={'/logout'}><img src={'/assets/images/logout.png'} alt={""}/></a>
                </div>
            </header>
            {!collapsed &&
                <div className={"menu"}>
                    <section className={"menu-header"}>
                        <Link className={"menu-link-big"} to="/dashboard">tableau de bord</Link>
                        <Link className={"menu-link-big"} to="/account">mon compte</Link>
                        <Link className={"menu-link-big hidden"} to={"/dispatch/" + service}>dispatch</Link>
                        <Link className={"menu-link-big"} to="/servicenav">changer de service</Link>
                    </section>
                    <section className={"menu-scrollable"}>
                        <div className={"menu-item-list"}>
                            <section className={"menu-item"}>
                                <h2><span>Patient</span></h2>
                                <ul className={"menu-nav-list"}>
                                    {(user.grade.admin || (user.OnService && user.grade.rapport_view) || user.grade.rapport_HS) &&
                                        <li className={'menu-puce'}><Link to={'/patients/rapport'}
                                                                          className={'menu-link'}>rapports</Link></li>
                                    }
                                    {(user.grade.admin || (user.OnService && user.grade.dossier_view) || user.grade.dossier_HS) &&
                                        <li className={'menu-puce'}><Link to={'/patients/dossiers'}
                                                                          className={'menu-link'}>dossiers</Link></li>
                                    }
                                    {(user.grade.admin || (user.OnService && user.grade.poudretest_view) || user.grade.poudretest_HS) &&
                                        <li className={'menu-puce'}><Link to={'/patients/poudre'}
                                                                          className={'menu-link'}>tests de poudre</Link>
                                        </li>
                                    }
                                    <li className={'menu-puce'}><Link to={'/blackcodes/all'} className={'menu-link'}>BC
                                        - Incendies</Link></li>
                                </ul>
                            </section>
                            <section className={"menu-item"}>
                                <h2><span>Factures</span></h2>
                                <ul className={"menu-nav-list"}>
                                    {(user.grade.admin || (user.onService && user.grade.facture_view) || user.grade.facture_HS) &&
                                        <li className={'menu-puce'}><Link to={'/factures'}
                                                                          className={'menu-link'}>factures</Link></li>
                                    }

                                </ul>
                            </section>
                            <section className={"menu-item hidden"}>
                                <h2><span>Formations</span></h2>
                                <ul className={"menu-nav-list"}>
                                    <li className={'menu-puce'}><Link to={'/formation/questionnaires'}
                                                                      className={'menu-link'}>questionnaires</Link></li>
                                    <li className={'menu-puce'}><Link to={'/formation/admin'}
                                                                      className={'menu-link'}>création</Link></li>
                                </ul>
                            </section>
                            <section className={"menu-item"}>
                                <h2><span>Logistique</span></h2>
                                <ul className={"menu-nav-list"}>
                                    <li className={'menu-puce hidden'}><Link to={'/' + service + '/logistique/stock/view'}
                                                                      className={'menu-link'}>gestion des stocks</Link>
                                    </li>
                                    {(user.grade.admin || (user.OnService && user.pilote)) &&
                                        <li className={'menu-puce'}><Link to={'/' + service + '/vols'}
                                        className={'menu-link'}>vols</Link></li>
                                    }

                                </ul>
                            </section>
                            <section className={"menu-item"}>
                                <h2><span>Personnel</span></h2>
                                <ul className={"menu-nav-list"}>
                                    {(user.grade.admin || user.grade.view_grade_list) &&
                                        <li className={'menu-puce'}><Link to={'/' + service + '/personnel/grade'}
                                                                          className={'menu-link'}>grade</Link></li>
                                    }
                                    {(user.grade.admin || user.grade.view_rappportHoraire) &&
                                        <li className={'menu-puce'}><Link to={'/' + service + '/personnel/horaire'}
                                                                          className={'menu-link'}>rapport horaire</Link>
                                        </li>
                                    }
                                    {(user.grade.admin || user.grade.view_PersonnelList) &&
                                        <li className={'menu-puce'}><Link to={'/' + service + '/personnel/personnel'}
                                                                          className={'menu-link'}>liste du
                                            personnel</Link></li>
                                    }
                                    {(user.grade.admin || user.grade.viewAll_service_req || user.grade.viewAll_prime_req || user.grade.viewAll_absences_req) &&
                                        <li className={'menu-puce'}><Link to={'/' + service + '/personnel/demandes'}
                                                                          className={'menu-link'}>demandes</Link></li>
                                    }
                                </ul>
                            </section>
                            <section className={"menu-item"}>
                                <h2><span>Gestion MDT</span></h2>
                                <ul className={"menu-nav-list"}>
                                    {(user.grade.admin || user.grade.modify_discordChann) &&
                                        <li className={'menu-puce'}><Link to={'/global/mdt/discord'}
                                                                          className={'menu-link'}>discord</Link></li>
                                    }
                                    {(user.grade.admin || user.grade.view_logs) &&
                                        <li className={'menu-puce'}><Link to={'/' + service + '/mdt/logs'}
                                                                          className={'menu-link'}>logs</Link></li>
                                    }
                                    {(user.grade.admin || user.grade.modify_gestionContent) &&
                                        <li className={'menu-puce'}><Link to={'/' + service + '/mdt/content'}
                                                                          className={'menu-link'}>gestion des
                                            contenus</Link></li>
                                    }
                                    {(user.grade.admin || user.grade.post_annonces || user.grade.post_actualities || user.grade.edit_infos_utils) &&
                                        <li className={'menu-puce'}><Link to={'/' + service + '/mdt/infos'}
                                                                          className={'menu-link'}>info / annonces</Link>
                                        </li>
                                    }
                                </ul>
                            </section>
                        </div>
                    </section>
                    <section className={"menu-footer"}>
                        <h3>Design & développement
                            Simon Lou - Copyright ©</h3>
                    </section>
                </div>
            }
            <div className={'app-page-container'}>
                <UserContext.Provider value={user}>
                    <Route path={'/account'} component={MyAccount}/>
                    <Route path={'/dashboard'} component={Dashboard}/>


                    <Route path={'/patients/rapport'} component={Rapport}/>
                    <Route path={'/patients/dossiers'} component={DossiersPatient}/>
                    <Route path={'/patients/:patientId/view'} component={RapportReview}/>
                    <Route path={'/patients/:patientId/psy'} component={Psycology}/>
                    <Route path={'/patients/poudre'} component={TestPoudre}/>
                    <Route path={'/factures/'} component={FactureList}/>

                    <Route path={'/blackcodes/all'} component={GlobalView}/>
                    <Route path={'/blackcodes/medic/:bcID'} component={MedicBC}/>
                    <Route path={'/blackcodes/fire/:bcID'} component={FireBC}/>

                    <Route path={'/:service/logistique/stock/view'} component={GestionStocks}/>
                    <Route path={'/:service/logistique/stock/settings'} component={StockSettings}/>
                    <Route path={'/:service/vols'} component={Vols}/>

                    <Route path={'/:service/personnel/grade'} component={Grade}/>
                    <Route path={'/:service/personnel/horaire'} component={RapportHoraire}/>
                    <Route path={'/:service/personnel/personnel'} component={ListPersonnel}/>
                    <Route path={'/:service/personnel/demandes'} component={Demandes}/>
                    <Route path={'/personnel/fiche/:userId'} component={FichePersonnel}/>

                    <Route path={'/global/mdt/discord'} component={DiscordBots}/>
                    <Route path={'/:service/mdt/logs'} component={Logs}/>
                    <Route path={'/:service/mdt/content'} component={ContentManager}/>
                    <Route path={'/:service/mdt/infos'} component={AnnoncesInfos}/>


                    <Route path='/servicenav' component={ServiceNav}/>

                </UserContext.Provider>
            </div>
        </div>)
    }
}

export default Layout;
